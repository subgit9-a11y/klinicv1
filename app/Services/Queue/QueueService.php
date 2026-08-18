<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Models\Appointment;
use App\Models\AppointmentToken;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages the walk-in token queue: arrival, calling the next patient,
 * starting/finishing consultations, and skipping no-shows.
 *
 * Token lifecycle: WAITING → CALLED → IN_PROGRESS → DONE  (or SKIPPED).
 * Each operation runs inside a transaction with a row lock on the affected
 * token so two receptionists cannot call the same patient simultaneously.
 */
class QueueService
{
    public function __construct(
        private readonly AppointmentService $appointments,
    ) {}

    /**
     * Tokens for a given doctor + date, ordered by token number.
     */
    public function forDoctor(User $doctor, string $date): Collection
    {
        return AppointmentToken::where('tenant_id', $doctor->tenant_id)
            ->where('user_id', $doctor->id)
            ->whereBetween('appointment_date', [now()->parse($date)->startOfDay(), now()->parse($date)->endOfDay()])
            ->orderBy('token_number')
            ->with('appointment.patient:id,k360_uid,first_name,last_name,phone')
            ->get();
    }

    /**
     * All tokens across all doctors for a date, grouped by doctor.
     *
     * @return Collection<int, AppointmentToken>
     */
    public function forDate(string $date): Collection
    {
        $tenantId = app(TenantContext::class)->id();

        return AppointmentToken::where('tenant_id', $tenantId)
            ->whereBetween('appointment_date', [now()->parse($date)->startOfDay(), now()->parse($date)->endOfDay()])
            ->orderBy('user_id')
            ->orderBy('token_number')
            ->with(['appointment.patient:id,k360_uid,first_name,last_name,phone', 'doctor:id,name'])
            ->get();
    }

    /**
     * Call the next WAITING token for a doctor on a given date.
     */
    public function callNext(User $doctor, string $date, User $by): ?AppointmentToken
    {
        return DB::transaction(function () use ($doctor, $date, $by) {
            $token = AppointmentToken::where('tenant_id', $doctor->tenant_id)
                ->where('user_id', $doctor->id)
                ->whereBetween('appointment_date', [now()->parse($date)->startOfDay(), now()->parse($date)->endOfDay()])
                ->where('status', 'WAITING')
                ->orderBy('token_number')
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                return null;
            }

            $token->update(['status' => 'CALLED', 'called_at' => now()]);

            // Advance the linked appointment to CHECKED_IN (valid transition from SCHEDULED/CONFIRMED).
            $appt = $token->appointment;
            if ($appt && in_array($appt->status, ['SCHEDULED', 'CONFIRMED'], true)) {
                $this->appointments->changeStatus($appt, 'CHECKED_IN', $by, 'Called from queue');
            }

            return $token->fresh();
        });
    }

    /**
     * Begin consultation for a token (CALLED → IN_PROGRESS).
     */
    public function startConsultation(AppointmentToken $token, User $by): AppointmentToken
    {
        return DB::transaction(function () use ($token, $by) {
            $token = $this->lockToken($token);

            if ($token->status !== 'CALLED') {
                throw ValidationException::withMessages([
                    'token' => 'Only a CALLED token can start consultation (current: '.$token->status.').',
                ]);
            }

            $token->update(['status' => 'IN_PROGRESS']);

            $appt = $token->appointment;
            if ($appt && $appt->status === 'CHECKED_IN') {
                $this->appointments->changeStatus($appt, 'IN_CONSULTATION', $by, 'Consultation started');
            }

            return $token->fresh();
        });
    }

    /**
     * Finish a token (IN_PROGRESS → DONE) and complete the appointment.
     */
    public function complete(AppointmentToken $token, User $by): AppointmentToken
    {
        return DB::transaction(function () use ($token, $by) {
            $token = $this->lockToken($token);

            if (! in_array($token->status, ['IN_PROGRESS', 'CALLED'], true)) {
                throw ValidationException::withMessages([
                    'token' => 'Cannot complete a token that is '.$token->status.'.',
                ]);
            }

            $token->update(['status' => 'DONE']);

            $appt = $token->appointment;
            if ($appt && in_array($appt->status, ['CHECKED_IN', 'IN_CONSULTATION', 'CONFIRMED', 'SCHEDULED'], true)) {
                try {
                    $this->appointments->changeStatus($appt, 'COMPLETED', $by, 'Queue completed');
                } catch (ValidationException) {
                    // If the appointment can't transition to COMPLETED directly (e.g. SCHEDULED),
                    // force-complete it since the consultation physically happened.
                    $appt->update(['status' => 'COMPLETED', 'completed_at' => now()]);
                }
            }

            return $token->fresh();
        });
    }

    /**
     * Skip a no-show token (WAITING/CALLED → SKIPPED) and mark appointment NO_SHOW.
     */
    public function skip(AppointmentToken $token, User $by): AppointmentToken
    {
        return DB::transaction(function () use ($token, $by) {
            $token = $this->lockToken($token);

            if (in_array($token->status, ['DONE', 'SKIPPED'], true)) {
                throw ValidationException::withMessages([
                    'token' => 'Cannot skip a token that is already '.$token->status.'.',
                ]);
            }

            $token->update(['status' => 'SKIPPED']);

            $appt = $token->appointment;
            if ($appt && ! in_array($appt->status, ['COMPLETED', 'CANCELLED', 'NO_SHOW'], true)) {
                try {
                    $this->appointments->changeStatus($appt, 'NO_SHOW', $by, 'Skipped from queue');
                } catch (ValidationException) {
                    $appt->update(['status' => 'NO_SHOW']);
                }
            }

            return $token->fresh();
        });
    }

    /**
     * Reset a skipped token back to WAITING (patient showed up late).
     */
    public function recall(AppointmentToken $token, User $by): AppointmentToken
    {
        return DB::transaction(function () use ($token) {
            $token = $this->lockToken($token);

            if ($token->status !== 'SKIPPED') {
                throw ValidationException::withMessages([
                    'token' => 'Only a SKIPPED token can be recalled.',
                ]);
            }

            $token->update(['status' => 'WAITING', 'called_at' => null]);

            $appt = $token->appointment;
            if ($appt && $appt->status === 'NO_SHOW') {
                $appt->update(['status' => 'SCHEDULED']);
            }

            return $token->fresh();
        });
    }

    /**
     * Queue statistics for a doctor + date.
     *
     * @return array<string, int>
     */
    public function stats(User $doctor, string $date): array
    {
        $tokens = $this->forDoctor($doctor, $date);

        return [
            'total' => $tokens->count(),
            'waiting' => $tokens->where('status', 'WAITING')->count(),
            'called' => $tokens->where('status', 'CALLED')->count(),
            'in_progress' => $tokens->where('status', 'IN_PROGRESS')->count(),
            'done' => $tokens->where('status', 'DONE')->count(),
            'skipped' => $tokens->where('status', 'SKIPPED')->count(),
        ];
    }

    /**
     * The currently-active token (CALLED or IN_PROGRESS) for a doctor + date.
     */
    public function current(User $doctor, string $date): ?AppointmentToken
    {
        return AppointmentToken::where('tenant_id', $doctor->tenant_id)
            ->where('user_id', $doctor->id)
            ->whereBetween('appointment_date', [now()->parse($date)->startOfDay(), now()->parse($date)->endOfDay()])
            ->whereIn('status', ['CALLED', 'IN_PROGRESS'])
            ->orderByDesc('called_at')
            ->first();
    }

    protected function lockToken(AppointmentToken $token): AppointmentToken
    {
        return AppointmentToken::where('id', $token->id)->lockForUpdate()->firstOrFail();
    }
}
