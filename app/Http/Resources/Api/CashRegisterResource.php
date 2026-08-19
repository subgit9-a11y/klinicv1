<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'opening_balance' => $this->opening_balance_cents / 100,
            'closing_balance' => $this->closing_balance_cents / 100,
            'actual_balance' => $this->actual_balance_cents !== null ? $this->actual_balance_cents / 100 : null,
            'variance' => $this->variance_cents !== null ? $this->variance_cents / 100 : null,
            'currency' => 'INR',
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
