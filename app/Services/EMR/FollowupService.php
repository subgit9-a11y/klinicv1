<?php

declare(strict_types=1);

namespace App\Services\EMR;

use App\Models\Followup;
use App\Models\Patient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Schedules and tracks patient follow-ups. The scheduler command
 * (klinic:send-followup-reminders) consumes PENDING follow-ups whose
 * due_date has arrived to dispatch reminders.
 */
class FollowupService
{
    private const STATUSES = ['PENDING', 'COMPLETED', 'CANCELLED'];

    /**
     * Schedule a follow-up for a patient, optionally linked to a consultation
     * or appointment.
     *
     * @param  array{patient_id:int, consultation_id?:int, appointment_id?:int, due_date:string, instructions?:?string, status?:string}  $attributes
     */
    public function schedule(array $attributes): Followup
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'patient_id' => ['required', 'integer'],
            'consultation_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'due_date' => ['required', 'date'],
            'instructions' => ['nullable', 'string'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
        ]);

        Patient::where('id', $validated['patient_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return Followup::create([
            'tenant_id' => $tenantId,
            'patient_id' => $validated['patient_id'],
            'consultation_id' => $validated['consultation_id'] ?? null,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'due_date' => $validated['due_date'],
            'instructions' => $validated['instructions'] ?? null,
            'status' => $validated['status'] ?? 'PENDING',
        ]);
    }

    /**
     * Mark a follow-up as completed (or cancelled).
     */
    public function updateStatus(Followup $followup, string $status): Followup
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $followup->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['tenant' => 'Follow-up belongs to a different tenant.']);
        }

        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid follow-up status.']);
        }

        $followup->update(['status' => $status]);

        return $followup->fresh();
    }

    /**
     * @return Collection<int, Followup>
     */
    public function forPatient(int $patientId): Collection
    {
        $tenantId = $this->requireTenant();

        return Followup::where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderBy('due_date')
            ->get();
    }

    private function requireTenant(): int
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => 'No active tenant context.']);
        }

        return $tenantId;
    }
}
