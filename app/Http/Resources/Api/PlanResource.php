<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'price' => $this->price_cents / 100,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'is_active' => (bool) $this->is_active,
            'max_users' => $this->max_users,
            'max_doctors' => $this->max_doctors,
            'max_patients' => $this->max_patients,
            'ai_request_limit_per_day' => $this->ai_request_limit_per_day,
            'ipd_enabled' => (bool) $this->ipd_enabled,
            'treatments_enabled' => (bool) $this->treatments_enabled,
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn ($f) => [
                'key' => $f->feature_key,
                'label' => $f->label ?? $f->feature_key,
                'included' => (bool) $f->included,
            ])),
        ];
    }
}
