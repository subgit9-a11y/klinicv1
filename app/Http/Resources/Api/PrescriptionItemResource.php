<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'medicine' => $this->medicine,
            'form' => $this->form,
            'strength' => $this->strength,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'route' => $this->route,
            'quantity' => $this->quantity,
            'instructions' => $this->instructions,
            'timing' => $this->timing,
            'anupana' => $this->anupana,
            'external_application' => (bool) $this->external_application,
        ];
    }
}
