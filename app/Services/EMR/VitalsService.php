<?php

declare(strict_types=1);

namespace App\Services\EMR;

use App\Models\Vital;
use Illuminate\Database\Eloquent\Collection;

/**
 * Records and retrieves patient vitals. BMI is auto-calculated from
 * height (cm) and weight (kg) when both are provided.
 */
class VitalsService
{
    /**
     * Record a vital sign entry for a patient.
     *
     * @param  array{systolic_bp?:?string,diastolic_bp?:?string,pulse?:?string,temperature?:?string,respiratory_rate?:?string,spo2?:?string,height?:?string,weight?:?string,bmi?:?string,pain_score?:?string,custom_vitals?:array}  $attributes
     */
    public function record(int $patientId, array $attributes, ?int $consultationId = null, ?int $recordedBy = null): Vital
    {
        $attributes['patient_id'] = $patientId;
        $attributes['consultation_id'] = $consultationId;
        $attributes['recorded_by'] = $recordedBy ?? auth()->id();
        $attributes['recorded_at'] = now();

        // Auto-calculate BMI if height and weight are present but BMI is not.
        if (! isset($attributes['bmi']) && isset($attributes['height']) && isset($attributes['weight'])) {
            $heightM = (float) $attributes['height'] / 100;
            $weightKg = (float) $attributes['weight'];
            if ($heightM > 0 && $weightKg > 0) {
                $attributes['bmi'] = number_format($weightKg / ($heightM * $heightM), 1, '.', '');
            }
        }

        return Vital::create($attributes);
    }

    /**
     * Get all vitals for a patient, newest first.
     *
     * @return Collection<int, Vital>
     */
    public function forPatient(int $patientId): Collection
    {
        return Vital::where('patient_id', $patientId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Get the latest vital signs for a patient (e.g. for Patient 360 header).
     */
    public function latestForPatient(int $patientId): ?Vital
    {
        return Vital::where('patient_id', $patientId)
            ->orderByDesc('recorded_at')
            ->first();
    }
}
