<?php

declare(strict_types=1);

namespace App\Services\EMR;

use App\Models\ClinicalNote;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\User;
use App\Models\Vital;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Manages the clinical consultation lifecycle: open a draft from an
 * appointment, capture SOAP notes + vitals + diagnoses, support the
 * system-specific Ayurveda/Siddha/Homeopathy structured fields, and
 * complete or amend the record.
 *
 * V1 boundary: no pharmacy integration. The prescription block is created
 * in Phase 13 (Prescriptions & Vitals).
 */
class ConsultationService
{
    private const SYSTEMS = ['GENERAL', 'AYURVEDA', 'SIDDHA', 'HOMEOPATHY'];

    private const TYPES = ['OPD', 'ONLINE', 'FOLLOW_UP', 'IPD'];

    private const NOTE_TYPES = ['PROGRESS', 'NURSING', 'OBSERVATION', 'OTHER'];

    private const DIAGNOSIS_TYPES = ['PRIMARY', 'SECONDARY', 'DIFFERENTIAL', 'PROVISIONAL'];

    /**
     * Start a new draft consultation, optionally linked to an appointment.
     *
     * @param  array{patient_id: int, appointment_id?: int, medicine_system?: string, consultation_type?: string}  $attributes
     */
    public function start(array $attributes, User $doctor): Consultation
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'patient_id' => ['required', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'medicine_system' => ['nullable', 'in:'.implode(',', self::SYSTEMS)],
            'consultation_type' => ['nullable', 'in:'.implode(',', self::TYPES)],
        ]);

        $patient = Patient::where('id', $validated['patient_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $system = $validated['medicine_system'] ?? 'GENERAL';
        // Default consultation type from the linked appointment, if present.
        $type = $validated['consultation_type'] ?? 'OPD';

        return Consultation::create([
            'tenant_id' => $tenantId,
            'patient_id' => $patient->id,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'user_id' => $doctor->id,
            'medicine_system' => $system,
            'consultation_type' => $type,
            'status' => 'DRAFT',
        ]);
    }

    /**
     * Update SOAP + system-specific fields on a draft consultation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Consultation $consultation, array $attributes, User $doctor): Consultation
    {
        $this->assertSameTenant($consultation);

        $validated = Validator::validate($attributes, [
            'chief_complaint' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'examination' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'diagnosis_summary' => ['nullable', 'string'],
            'treatment_plan' => ['nullable', 'string'],
            'advice' => ['nullable', 'string'],
            'follow_up_instructions' => ['nullable', 'string'],
            'follow_up_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'system_specific' => ['nullable', 'array'],
            'medicine_system' => ['nullable', 'in:'.implode(',', self::SYSTEMS)],
            'consultation_type' => ['nullable', 'in:'.implode(',', self::TYPES)],
        ]);

        $consultation->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return $consultation->fresh();
    }

    /**
     * Record vitals for a patient, optionally linked to a consultation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordVitals(array $attributes, User $recorder): Vital
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'patient_id' => ['required', 'integer'],
            'consultation_id' => ['nullable', 'integer'],
            'systolic_bp' => ['nullable', 'string', 'max:8'],
            'diastolic_bp' => ['nullable', 'string', 'max:8'],
            'pulse' => ['nullable', 'string', 'max:8'],
            'temperature' => ['nullable', 'string', 'max:8'],
            'respiratory_rate' => ['nullable', 'string', 'max:8'],
            'spo2' => ['nullable', 'string', 'max:8'],
            'height' => ['nullable', 'string', 'max:8'],
            'weight' => ['nullable', 'string', 'max:8'],
            'bmi' => ['nullable', 'string', 'max:8'],
            'pain_score' => ['nullable', 'string', 'max:8'],
            'custom_vitals' => ['nullable', 'array'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['recorded_by'] = $recorder->id;
        $validated['recorded_at'] = $validated['recorded_at'] ?? now()->toDateTimeString();

        // Auto-compute BMI when height (cm) and weight (kg) are numeric.
        if (empty($validated['bmi']) && is_numeric($validated['height'] ?? null) && is_numeric($validated['weight'] ?? null)) {
            $h = (float) $validated['height'] / 100;
            if ($h > 0) {
                $validated['bmi'] = (string) round((float) $validated['weight'] / ($h * $h), 1);
            }
        }

        return Vital::create($validated);
    }

    /**
     * Add a diagnosis to a consultation.
     *
     * @param  array{name: string, code?: string, system?: string, type?: string, notes?: string}  $attributes
     */
    public function addDiagnosis(Consultation $consultation, array $attributes, User $doctor): Diagnosis
    {
        $this->assertSameTenant($consultation);

        $validated = Validator::validate($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'system' => ['nullable', 'string', 'max:24'],
            'type' => ['nullable', 'in:'.implode(',', self::DIAGNOSIS_TYPES)],
            'notes' => ['nullable', 'string'],
        ]);

        $system = $validated['system'] ?? $consultation->medicine_system;
        if ($system === 'GENERAL') {
            $system = null;
        }

        return Diagnosis::create([
            'tenant_id' => $consultation->tenant_id,
            'patient_id' => $consultation->patient_id,
            'consultation_id' => $consultation->id,
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'system' => $system,
            'type' => $validated['type'] ?? 'PRIMARY',
            'notes' => $validated['notes'] ?? null,
        ]);
    }

    /**
     * Add a free-text clinical note (progress/nursing/observation).
     */
    public function addNote(int $patientId, string $content, User $author, string $noteType = 'PROGRESS', ?int $consultationId = null): ClinicalNote
    {
        $tenantId = $this->requireTenant();

        if (! in_array($noteType, self::NOTE_TYPES, true)) {
            throw ValidationException::withMessages(['note_type' => 'Invalid note type.']);
        }

        return ClinicalNote::create([
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'consultation_id' => $consultationId,
            'user_id' => $author->id,
            'note_type' => $noteType,
            'content' => $content,
        ]);
    }

    /**
     * Finalise a draft consultation — locks the SOAP record and stamps
     * completed_at. Once completed, further edits require amend().
     */
    public function complete(Consultation $consultation, User $doctor): Consultation
    {
        $this->assertSameTenant($consultation);

        if ($consultation->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => 'Only a DRAFT consultation can be completed (current: '.$consultation->status.').',
            ]);
        }

        if (blank($consultation->chief_complaint) && blank($consultation->diagnosis_summary) && $consultation->diagnoses()->count() === 0) {
            throw ValidationException::withMessages([
                'chief_complaint' => 'A consultation requires at least a chief complaint, diagnosis summary, or a diagnosis before completion.',
            ]);
        }

        return DB::transaction(function () use ($consultation) {
            $consultation->update(['status' => 'COMPLETED', 'completed_at' => now()]);

            // Mark linked appointment as completed if still in consultation.
            $appt = $consultation->appointment;
            if ($appt && $appt->status === 'IN_CONSULTATION') {
                $appt->update(['status' => 'COMPLETED', 'completed_at' => now()]);
            }

            return $consultation->fresh();
        });
    }

    /**
     * Reopen a completed consultation for amendment. The original record
     * is preserved; an audit entry is left on the diagnosis_summary.
     */
    public function amend(Consultation $consultation, User $doctor, string $reason): Consultation
    {
        $this->assertSameTenant($consultation);

        if ($consultation->status !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'status' => 'Only a COMPLETED consultation can be amended.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'An amendment reason is required.']);
        }

        return DB::transaction(function () use ($consultation, $reason) {
            $consultation->update(['status' => 'AMENDED']);
            $consultation->update(['status' => 'COMPLETED']);
            $audit = '['.now()->format('Y-m-d H:i').'] Amendment: '.$reason;
            $consultation->update([
                'diagnosis_summary' => trim(($consultation->diagnosis_summary ?? '')."\n\n".$audit),
            ]);

            return $consultation->fresh();
        });
    }

    /**
     * Fetch the consultation history for a patient.
     *
     * @return Collection<int, Consultation>
     */
    public function forPatient(int $patientId): Collection
    {
        $tenantId = $this->requireTenant();

        return Consultation::where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->with(['doctor:id,name', 'diagnoses', 'vitals'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * System-specific structured-field validators. Each traditional medicine
     * system has its own JSON schema stored in `system_specific`.
     *
     * @return array<string, mixed>
     */
    public function validateSystemSpecific(string $system, array $fields): array
    {
        $rules = match ($system) {
            'AYURVEDA' => [
                'prakriti' => ['nullable', 'string', 'max:32'],
                'vikriti' => ['nullable', 'string', 'max:32'],
                'dosha' => ['nullable', 'string', 'max:32'],
                'srotas' => ['nullable', 'string'],
                'agni' => ['nullable', 'string', 'max:32'],
                'ama' => ['nullable', 'string', 'max:32'],
                'samprapti' => ['nullable', 'string'],
                'rogi_pareeksha' => ['nullable', 'string'],
                'roga_pareeksha' => ['nullable', 'string'],
            ],
            'SIDDHA' => [
                'mukkutram' => ['nullable', 'string', 'max:32'],
                'udal_thathukkal' => ['nullable', 'string'],
                'naadi' => ['nullable', 'string', 'max:32'],
                'neerkuri' => ['nullable', 'string'],
                'neikuri' => ['nullable', 'string'],
                'envagai_thervu' => ['nullable', 'string'],
            ],
            'HOMEOPATHY' => [
                'symptoms' => ['nullable', 'string'],
                'modalities' => ['nullable', 'string'],
                'constitution' => ['nullable', 'string', 'max:64'],
                'repertory' => ['nullable', 'string'],
                'remedy' => ['nullable', 'string', 'max:120'],
                'potency' => ['nullable', 'string', 'max:32'],
                'dose' => ['nullable', 'string', 'max:64'],
            ],
            default => [],
        };

        if ($rules === []) {
            return [];
        }

        return Validator::validate($fields, $rules);
    }

    private function requireTenant(): int
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => 'No active tenant context.']);
        }

        return $tenantId;
    }

    private function assertSameTenant(Consultation $consultation): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $consultation->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['tenant' => 'Consultation belongs to a different tenant.']);
        }
    }
}
