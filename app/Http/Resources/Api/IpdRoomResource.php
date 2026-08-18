<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpdRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ipd_ward_id' => $this->ipd_ward_id,
            'room_number' => $this->room_number,
            'type' => $this->type,
            'status' => $this->status,
            'ward' => IpdWardResource::make($this->whenLoaded('ward')),
        ];
    }
}
