<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'prescriber_id' => $this->user_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'patient' => PatientResource::make($this->whenLoaded('patient')),
            'items' => PrescriptionItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
