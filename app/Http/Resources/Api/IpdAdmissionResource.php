<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class IpdAdmissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ipd_number' => $this->ipd_number,
            'patient_id' => $this->patient_id,
            'ipd_bed_id' => $this->ipd_bed_id,
            'admitting_doctor_id' => $this->admitting_doctor_id,
            'admission_type' => $this->admission_type,
            'admission_reason' => $this->admission_reason,
            'provisional_diagnosis' => $this->provisional_diagnosis,
            'status' => $this->status,
            'admitted_at' => $this->admitted_at?->toIso8601String(),
            'discharged_at' => $this->discharged_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'patient' => PatientResource::make($this->whenLoaded('patient')),
        ];
    }
}
