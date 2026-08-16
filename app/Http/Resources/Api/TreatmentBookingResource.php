<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentBookingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'treatment_service_id' => $this->treatment_service_id,
            'therapist_id' => $this->therapist_id,
            'treatment_room_id' => $this->treatment_room_id,
            'booking_date' => $this->booking_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'payment_mode' => $this->payment_mode,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'patient' => PatientResource::make($this->whenLoaded('patient')),
        ];
    }
}
