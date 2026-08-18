<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount_cents / 100,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'cash_register_id' => $this->cash_register_id,
            'expense_date' => $this->expense_date?->toDateString(),
            'receipt_path' => $this->receipt_path,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
