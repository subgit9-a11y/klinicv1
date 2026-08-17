<?php

declare(strict_types=1);

namespace App\Services\EMR;

use App\Models\Investigation;
use App\Models\Patient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Orders and tracks laboratory/diagnostic investigations for a patient.
 * The Patient 360 investigations tab displays these; results are appended
 * via InvestigationResult once the lab reports back.
 */
class InvestigationService
{
    private const STATUSES = ['REQUESTED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    private const CATEGORIES = ['LAB', 'RADIOLOGY', 'CARDIAC', 'PATHOLOGY', 'OTHER'];

    /**
     * Order a new investigation.
     *
     * @param  array{patient_id:int, consultation_id?:int, name:string, category?:string, status?:string, requested_at?:string}  $attributes
     */
    public function order(array $attributes): Investigation
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'patient_id' => ['required', 'integer'],
            'consultation_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:'.implode(',', self::CATEGORIES)],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'requested_at' => ['nullable', 'date'],
        ]);

        Patient::where('id', $validated['patient_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return Investigation::create([
            'tenant_id' => $tenantId,
            'patient_id' => $validated['patient_id'],
            'consultation_id' => $validated['consultation_id'] ?? null,
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'LAB',
            'status' => $validated['status'] ?? 'REQUESTED',
            'requested_at' => $validated['requested_at'] ?? now()->toDateTimeString(),
        ]);
    }

    /**
     * Update an investigation's status (e.g. mark completed with result).
     *
     * @param  array{status?:string, completed_at?:string, name?:string}  $attributes
     */
    public function update(Investigation $investigation, array $attributes): Investigation
    {
        $this->assertSameTenant($investigation);

        $validated = Validator::validate($attributes, [
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'completed_at' => ['nullable', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'in:'.implode(',', self::CATEGORIES)],
        ]);

        if (($validated['status'] ?? null) === 'COMPLETED' && empty($validated['completed_at'])) {
            $validated['completed_at'] = now()->toDateTimeString();
        }

        $investigation->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return $investigation->fresh();
    }

    /**
     * @return Collection<int, Investigation>
     */
    public function forPatient(int $patientId): Collection
    {
        $tenantId = $this->requireTenant();

        return Investigation::where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderByDesc('requested_at')
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

    private function assertSameTenant(Investigation $investigation): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $investigation->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['tenant' => 'Investigation belongs to a different tenant.']);
        }
    }
}
