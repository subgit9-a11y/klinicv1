<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'medicine_system' => $this->medicine_system,
            'duration_minutes' => $this->duration_minutes,
            'price' => $this->price_cents !== null ? $this->price_cents / 100 : null,
            'currency' => $this->currency,
            'description' => $this->description,
            'requires_therapist' => (bool) $this->requires_therapist,
            'requires_room' => (bool) $this->requires_room,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
