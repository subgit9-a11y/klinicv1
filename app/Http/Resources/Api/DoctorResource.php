<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'specialization' => $this->specialization,
            'registration_number' => $this->registration_number,
            'medicine_system' => $this->medicine_system,
            'consultation_fee' => $this->consultation_fee_cents !== null ? $this->consultation_fee_cents / 100 : null,
            'followup_fee' => $this->followup_fee_cents !== null ? $this->followup_fee_cents / 100 : null,
            'is_active' => (bool) $this->is_active,
            'designation' => $this->designation,
            'availability' => DoctorAvailabilityResource::collection($this->whenLoaded('availability')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
