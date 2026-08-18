<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpdBedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ipd_room_id' => $this->ipd_room_id,
            'bed_number' => $this->bed_number,
            'status' => $this->status,
            'daily_rate' => $this->daily_rate_cents !== null ? $this->daily_rate_cents / 100 : null,
            'room' => IpdRoomResource::make($this->whenLoaded('room')),
        ];
    }
}
