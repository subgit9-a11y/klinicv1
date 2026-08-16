<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'source' => $this->source,
            'subtotal' => $this->subtotal_cents / 100,
            'discount' => $this->discount_cents / 100,
            'tax' => $this->tax_cents / 100,
            'total' => $this->total_cents / 100,
            'amount_paid' => $this->amount_paid_cents / 100,
            'amount_due' => $this->amount_due_cents / 100,
            'currency' => $this->currency,
            'patient' => PatientResource::make($this->whenLoaded('patient')),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
