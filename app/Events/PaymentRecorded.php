<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payment is recorded against an invoice (by BillingService).
 * Listeners dispatch a payment-receipt notification job to the patient.
 */
class PaymentRecorded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Invoice $invoice,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        $amount = $this->payment->amount_cents / 100;

        return [
            'patient_name' => $this->invoice->patient?->name ?? '',
            'amount' => number_format($amount, 2),
            'currency' => $this->invoice->currency,
            'invoice_number' => $this->invoice->invoice_number,
            'payment_number' => $this->payment->payment_number,
            'method' => $this->payment->method,
            'reference' => $this->payment->payment_number,
        ];
    }
}
