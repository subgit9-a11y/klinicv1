<?php

declare(strict_types=1);

namespace App\Services\Telemedicine;

use App\Contracts\VideoProviderInterface;
use App\Models\Teleconsultation;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Teleconsultation lifecycle: schedule → start → end → cancel.
 *
 * Meeting link creation is delegated to the configured VideoProvider
 * (GoogleMeetProvider). If no provider is configured, the teleconsultation
 * is still scheduled (status SCHEDULED) but the meeting_url stays null —
 * the doctor must supply a link manually. We NEVER fabricate a meeting URL.
 */
class TeleconsultationService
{
    public function __construct(
        private readonly VideoProviderInterface $video,
    ) {}

    /**
     * Schedule a teleconsultation, optionally linked to an appointment.
     *
     * @param  array{patient_id:int, user_id?:?int, appointment_id?:?int, scheduled_at?:string, duration_minutes?:int, title?:string}  $attributes
     */
    public function schedule(array $attributes): Teleconsultation
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'patient_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        return DB::transaction(function () use ($validated, $tenantId) {
            $teleconsultation = Teleconsultation::create([
                'patient_id' => $validated['patient_id'],
                'user_id' => $validated['user_id'] ?? null,
                'appointment_id' => $validated['appointment_id'] ?? null,
                'status' => 'SCHEDULED',
            ]);

            // Attempt to provision a real meeting link. On any failure the
            // teleconsultation remains scheduled; the doctor can start it
            // manually and supply a link.
            $start = isset($validated['scheduled_at'])
                ? Carbon::parse($validated['scheduled_at'])
                : now()->addMinutes(15);
            $end = $start->copy()->addMinutes($validated['duration_minutes'] ?? 30);
            $title = $validated['title'] ?? 'Klinic360 Teleconsultation';

            $this->tryCreateMeeting($teleconsultation, $title, $start, $end);

            return $teleconsultation->refresh();
        });
    }

    /**
     * Start a scheduled teleconsultation. If no meeting URL exists yet,
     * retry provisioning one now (provider may have been configured since).
     */
    public function start(Teleconsultation $teleconsultation): Teleconsultation
    {
        $this->assertSameTenant($teleconsultation);

        if (! in_array($teleconsultation->status, ['SCHEDULED', 'STARTED'], true)) {
            throw new \DomainException("Cannot start a {$teleconsultation->status} teleconsultation.");
        }

        if ($teleconsultation->meeting_url === null) {
            $this->tryCreateMeeting(
                $teleconsultation,
                'Klinic360 Teleconsultation #'.$teleconsultation->id,
                now(),
                now()->addMinutes(30),
            );
        }

        $teleconsultation->update([
            'status' => 'STARTED',
            'started_at' => $teleconsultation->started_at ?? now(),
        ]);

        return $teleconsultation->refresh();
    }

    public function end(Teleconsultation $teleconsultation): Teleconsultation
    {
        $this->assertSameTenant($teleconsultation);

        if ($teleconsultation->status !== 'STARTED') {
            throw new \DomainException('Only a STARTED teleconsultation can be ended.');
        }

        $teleconsultation->update([
            'status' => 'COMPLETED',
            'ended_at' => now(),
        ]);

        return $teleconsultation->refresh();
    }

    public function cancel(Teleconsultation $teleconsultation, ?string $reason = null): Teleconsultation
    {
        $this->assertSameTenant($teleconsultation);

        if (in_array($teleconsultation->status, ['COMPLETED', 'CANCELLED'], true)) {
            throw new \DomainException("Cannot cancel a {$teleconsultation->status} teleconsultation.");
        }

        // Release the upstream meeting if one was provisioned.
        if ($teleconsultation->meeting_id !== null) {
            try {
                $this->video->deleteMeeting($teleconsultation->meeting_id);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete upstream meeting on cancel', ['id' => $teleconsultation->id, 'error' => $e->getMessage()]);
            }
        }

        $teleconsultation->update([
            'status' => 'CANCELLED',
            'ended_at' => now(),
        ]);

        return $teleconsultation->refresh();
    }

    /**
     * Mark a scheduled teleconsultation as a no-show.
     */
    public function markNoShow(Teleconsultation $teleconsultation): Teleconsultation
    {
        $this->assertSameTenant($teleconsultation);

        if ($teleconsultation->status !== 'SCHEDULED') {
            throw new \DomainException('Only a SCHEDULED teleconsultation can be marked as no-show.');
        }

        $teleconsultation->update(['status' => 'NO_SHOW']);

        return $teleconsultation->refresh();
    }

    /**
     * @return Collection<int, Teleconsultation>
     */
    public function forPatient(int $patientId): Collection
    {
        $this->requireTenant();

        return Teleconsultation::where('patient_id', $patientId)
            ->latest()
            ->get();
    }

    private function tryCreateMeeting(Teleconsultation $teleconsultation, string $title, Carbon $start, Carbon $end): void
    {
        if (! $this->video->isConfigured()) {
            return;
        }

        try {
            $result = $this->video->createMeeting($title, $start, $end);
            if (($result['success'] ?? false) && ! empty($result['meeting_url'])) {
                $teleconsultation->update([
                    'meeting_id' => $result['meeting_id'] ?? null,
                    'meeting_url' => $result['meeting_url'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Teleconsultation meeting provisioning failed', ['id' => $teleconsultation->id, 'error' => $e->getMessage()]);
        }
    }

    private function requireTenant(): int
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => 'No active tenant context.']);
        }

        return $tenantId;
    }

    private function assertSameTenant(Teleconsultation $teleconsultation): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $teleconsultation->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['teleconsultation' => 'Teleconsultation belongs to a different tenant.']);
        }
    }
}
