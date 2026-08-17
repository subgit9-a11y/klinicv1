<?php

declare(strict_types=1);

namespace App\Services\EMR;

use App\Models\Patient;
use App\Models\PatientConsent;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records patient consents (treatment, surgery, data sharing, etc.).
 * The Patient 360 consent tab displays recorded consents; this service
 * is the only path to create or revoke them.
 */
class ConsentService
{
    private const TYPES = ['TREATMENT', 'SURGERY', 'TELECONSULTATION', 'DATA_SHARING', 'PROCEDURE', 'OTHER'];

    /**
     * Record a patient consent.
     *
     * @param  array{patient_id:int, consent_type:string, granted:bool, description?:?string, document_path?:?string, expires_at?:?string, consented_at?:?string}  $attributes
     * @param  int  $capturedBy  The user recording the consent.
     */
    public function record(array $attributes, int $capturedBy): PatientConsent
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'patient_id' => ['required', 'integer'],
            'consent_type' => ['required', 'in:'.implode(',', self::TYPES)],
            'granted' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'document_path' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'consented_at' => ['nullable', 'date'],
        ]);

        Patient::where('id', $validated['patient_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return PatientConsent::create([
            'tenant_id' => $tenantId,
            'patient_id' => $validated['patient_id'],
            'consent_type' => $validated['consent_type'],
            'granted' => $validated['granted'],
            'description' => $validated['description'] ?? null,
            'document_path' => $validated['document_path'] ?? null,
            'captured_by' => $capturedBy,
            'consented_at' => $validated['consented_at'] ?? now()->toDateTimeString(),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);
    }

    /**
     * Revoke a previously granted consent.
     */
    public function revoke(PatientConsent $consent): PatientConsent
    {
        $this->assertSameTenant($consent);

        $consent->update(['granted' => false]);

        return $consent->fresh();
    }

    /**
     * @return Collection<int, PatientConsent>
     */
    public function forPatient(int $patientId): Collection
    {
        $tenantId = $this->requireTenant();

        return PatientConsent::where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderByDesc('consented_at')
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

    private function assertSameTenant(PatientConsent $consent): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $consent->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['tenant' => 'Consent belongs to a different tenant.']);
        }
    }
}
