<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'supported_treatment_types' => $this->supported_treatment_types,
        ];
    }
}
