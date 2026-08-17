<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medicine_system' => $this->medicine_system,
            'consultation_type' => $this->consultation_type,
            'status' => $this->status,
            'chief_complaint' => $this->chief_complaint,
            'history' => $this->history,
            'examination' => $this->examination,
            'assessment' => $this->assessment,
            'diagnosis_summary' => $this->diagnosis_summary,
            'treatment_plan' => $this->treatment_plan,
            'advice' => $this->advice,
            'follow_up_instructions' => $this->follow_up_instructions,
            'follow_up_days' => $this->follow_up_days,
            'system_specific' => $this->system_specific,
            'patient' => PatientResource::make($this->whenLoaded('patient')),
            'doctor' => [
                'id' => $this->doctor?->id,
                'name' => $this->doctor?->name,
            ],
            'vitals' => $this->whenLoaded('vitals'),
            'diagnoses' => $this->whenLoaded('diagnoses'),
            'notes' => $this->whenLoaded('notes'),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
