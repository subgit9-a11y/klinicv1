<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\PaymentGatewayInterface;
use App\Events\PaymentRecorded;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Audit\AuditService;
use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Manages invoice lifecycle: creation, item addition, issuance,
 * payment recording (partial/full), refunds, and voiding.
 *
 * Invoice status transitions:
 *  DRAFT → ISSUED (on issue)
 *  ISSUED → PARTIALLY_PAID (partial payment)
 *  ISSUED/PARTIALLY_PAID → PAID (full payment)
 *  * → VOID (on void)
 *  PAID → REFUNDED (full refund)
 */
class BillingService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly CashRegisterService $cashRegister,
    ) {}

    /**
     * Create a draft invoice.
     *
     * @param  array{patient_id:int, appointment_id?:?int, consultation_id?:?int, ipd_admission_id?:?int, source?:string, currency?:string, notes?:?string}  $attributes
     */
    public function createInvoice(array $attributes): Invoice
    {
        return Invoice::create([
            'patient_id' => $attributes['patient_id'],
            'appointment_id' => $attributes['appointment_id'] ?? null,
            'consultation_id' => $attributes['consultation_id'] ?? null,
            'ipd_admission_id' => $attributes['ipd_admission_id'] ?? null,
            'invoice_number' => $this->generateInvoiceNumber(),
            'status' => 'DRAFT',
            'source' => $attributes['source'] ?? 'OPD',
            'currency' => $attributes['currency'] ?? 'INR',
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    /**
     * Add a line item to an invoice. Only allowed while DRAFT.
     *
     * @param  array{description:string, type?:string, quantity?:int, unit_price_cents:int, discount_cents?:int, currency?:string}  $item
     */
    public function addInvoiceItem(Invoice $invoice, array $item): InvoiceItem
    {
        if ($invoice->status !== 'DRAFT') {
            throw new \DomainException('Items can only be added to DRAFT invoices.');
        }

        $quantity = $item['quantity'] ?? 1;
        $discount = $item['discount_cents'] ?? 0;
        $unitPrice = $item['unit_price_cents'];

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $item['description'],
            'type' => $item['type'] ?? 'OTHER',
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'discount_cents' => $discount,
            'total_cents' => ($unitPrice * $quantity) - $discount,
            'currency' => $item['currency'] ?? $invoice->currency,
            'reference_type' => $item['reference_type'] ?? Invoice::class,
            'reference_id' => $item['reference_id'] ?? $invoice->id,
        ]);
    }

    /**
     * Finalize a draft invoice: calculate totals and mark as ISSUED.
     */
    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'DRAFT') {
            throw new \DomainException('Only DRAFT invoices can be issued.');
        }

        return DB::transaction(function () use ($invoice) {
            $subtotal = $invoice->items()->sum('total_cents');
            $discount = $invoice->items()->sum('discount_cents');
            $total = $subtotal;

            $invoice->update([
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'total_cents' => $total,
                'amount_due_cents' => $total - $invoice->amount_paid_cents,
                'status' => 'ISSUED',
                'issued_at' => now(),
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * Record a payment against an invoice. Handles partial payments and
     * transitions status accordingly.
     *
     * @param  array{method:string, amount_cents:int, gateway?:string, gateway_payment_id?:?string, gateway_order_id?:?string, cheque_number?:?string, bank_name?:?string, notes?:?string, cash_register_id?:?int, collected_by?:?int}  $attributes
     */
    public function recordPayment(Invoice $invoice, array $attributes): Payment
    {
        if (! in_array($invoice->status, ['ISSUED', 'PARTIALLY_PAID'])) {
            throw new \DomainException('Payments can only be recorded against ISSUED or PARTIALLY_PAID invoices.');
        }

        $amount = $attributes['amount_cents'];

        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be positive.');
        }

        // Gateway idempotency: a single gateway payment (identified by its
        // gateway_payment_id) must never be recorded twice — duplicate
        // webhook delivery or a retry must be a no-op. Manual payments have
        // no gateway_payment_id and skip this guard.
        $gatewayPaymentId = $attributes['gateway_payment_id'] ?? null;
        if ($gatewayPaymentId !== null) {
            $existing = Payment::where('gateway', $attributes['gateway'] ?? 'MANUAL')
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->where('status', 'SUCCESS')
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $payment = DB::transaction(function () use ($invoice, $attributes, $amount, $gatewayPaymentId) {
            // Lock the invoice row so two concurrent payments cannot both
            // read the same balance and create overpayments. Reload inside
            // the transaction to read the authoritative amount_due_cents.
            $invoice = Invoice::lockForUpdate()->find($invoice->id);

            // Re-check inside the transaction: a concurrent request may have
            // recorded this same gateway payment between the outer check and
            // the lock acquisition. The payments(gateway, gateway_payment_id)
            // unique constraint is the hard backstop; this avoids throwing.
            if ($gatewayPaymentId !== null) {
                $race = Payment::where('gateway', $attributes['gateway'] ?? 'MANUAL')
                    ->where('gateway_payment_id', $gatewayPaymentId)
                    ->where('status', 'SUCCESS')
                    ->lockForUpdate()
                    ->first();
                if ($race !== null) {
                    return $race;
                }
            }

            $currentDue = (int) $invoice->amount_due_cents;
            if ($amount > $currentDue) {
                throw new \DomainException(
                    'Payment amount ('.number_format($amount / 100, 2).' '.$invoice->currency.') exceeds outstanding balance ('.number_format($currentDue / 100, 2).' '.$invoice->currency.').'
                );
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'payment_number' => $this->generatePaymentNumber(),
                'gateway' => $attributes['gateway'] ?? 'MANUAL',
                'gateway_payment_id' => $attributes['gateway_payment_id'] ?? null,
                'gateway_order_id' => $attributes['gateway_order_id'] ?? null,
                'method' => $attributes['method'],
                'amount_cents' => $amount,
                'currency' => $invoice->currency,
                'status' => 'SUCCESS',
                'cheque_number' => $attributes['cheque_number'] ?? null,
                'bank_name' => $attributes['bank_name'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'cash_register_id' => $attributes['cash_register_id'] ?? null,
                'collected_by' => $attributes['collected_by'] ?? auth()->id(),
                'paid_at' => now(),
            ]);

            $totalPaid = $invoice->payments()->where('status', 'SUCCESS')->sum('amount_cents');
            $due = $invoice->total_cents - $totalPaid;

            $invoice->update([
                'amount_paid_cents' => $totalPaid,
                'amount_due_cents' => max(0, $due),
                'status' => $due <= 0 ? 'PAID' : 'PARTIALLY_PAID',
            ]);

            $this->audit->record('payment.recorded', 'billing', ['after' => ['amount_cents' => $amount, 'method' => $attributes['method']]], $payment);

            $this->cashRegister->recordPayment($payment);

            return $payment;
        });

        // Dispatch after commit so the receipt notification job only fires
        // on a persisted payment. Only successful payments warrant a receipt.
        if ($payment->status === 'SUCCESS') {
            Event::dispatch(new PaymentRecorded($payment, $payment->invoice));
        }

        return $payment;
    }

    /**
     * Issue a refund against a payment. The invoice status transitions
     * to REFUNDED if the full amount is refunded.
     *
     * @param  array{amount_cents:int, reason?:?string, gateway_refund_id?:?string}  $attributes
     */
    public function refund(Payment $payment, array $attributes): Refund
    {
        if ($payment->status !== 'SUCCESS') {
            throw new \DomainException('Only successful payments can be refunded.');
        }

        return DB::transaction(function () use ($payment, $attributes) {
            // Lock the payment row so concurrent refunds cannot both read the
            // same remaining balance and over-refund. The lock also serialises
            // the gateway call below, so a payment is never refunded twice at
            // the gateway.
            $payment = Payment::lockForUpdate()->find($payment->id);

            $alreadyRefunded = $payment->refunds()->where('status', 'SUCCESS')->sum('amount_cents');
            $remaining = $payment->amount_cents - $alreadyRefunded;

            if ($attributes['amount_cents'] > $remaining) {
                throw new \DomainException('Refund amount exceeds remaining payment balance.');
            }

            // Gateway payments must actually be refunded at the gateway —
            // a local record alone would not move the money. Fail closed:
            // if the gateway refund fails, NO local refund is recorded.
            // Manual payments (CASH/UPI/…, no gateway reference) stay
            // local-only — the clinic hands the money back itself.
            $gatewayRefundId = $attributes['gateway_refund_id'] ?? null;
            if ($payment->gateway !== null && $payment->gateway_payment_id !== null && $gatewayRefundId === null) {
                $gateway = app(PaymentGatewayInterface::class);

                if (! $gateway->isConfigured()) {
                    throw new \DomainException('Cannot refund a gateway payment while the payment gateway is not configured.');
                }

                $result = $gateway->refund(
                    $payment->gateway_payment_id,
                    (int) $attributes['amount_cents'],
                    $attributes['reason'] ?? null,
                );

                if (! ($result['success'] ?? false)) {
                    throw new \DomainException('Gateway refund failed: '.($result['message'] ?? 'unknown error'));
                }

                $gatewayRefundId = $result['refund_id'] ?? null;
            }

            $refund = Refund::create([
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'refund_number' => $this->generateRefundNumber(),
                'gateway_refund_id' => $gatewayRefundId,
                'amount_cents' => $attributes['amount_cents'],
                'currency' => $payment->currency,
                'status' => 'SUCCESS',
                'reason' => $attributes['reason'] ?? null,
                'processed_by' => auth()->id(),
                'refunded_at' => now(),
            ]);

            $totalRefunded = $alreadyRefunded + $attributes['amount_cents'];

            if ($totalRefunded >= $payment->amount_cents && $payment->invoice_id !== null) {
                $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);
                $invoice?->update(['status' => 'REFUNDED']);
            }

            $this->audit->record('refund.issued', 'billing', ['after' => ['amount_cents' => $attributes['amount_cents'], 'reason' => $attributes['reason'] ?? null]], $refund);

            $this->cashRegister->recordRefund($refund);

            return $refund;
        });
    }

    /**
     * Void an invoice. Only DRAFT or ISSUED invoices can be voided.
     */
    public function void(Invoice $invoice, ?string $reason = null): Invoice
    {
        if (in_array($invoice->status, ['PAID', 'REFUNDED', 'VOID'])) {
            throw new \DomainException("Cannot void a {$invoice->status} invoice.");
        }

        $invoice->update([
            'status' => 'VOID',
            'voided_at' => now(),
            'notes' => $reason ? $invoice->notes."\n[VOIDED] ".$reason : $invoice->notes,
        ]);

        return $invoice->refresh();
    }

    /**
     * Get all invoices for a patient.
     *
     * @return Collection<int, Invoice>
     */
    public function invoicesForPatient(int $patientId): Collection
    {
        return Invoice::where('patient_id', $patientId)
            ->orderByDesc('id')
            ->get();
    }

    private function generateInvoiceNumber(): string
    {
        return SequentialNumber::next('invoices', 'K360-INV', 'invoice_number');
    }

    private function generatePaymentNumber(): string
    {
        return SequentialNumber::next('payments', 'K360-PAY', 'payment_number');
    }

    private function generateRefundNumber(): string
    {
        return SequentialNumber::next('refunds', 'K360-REF', 'refund_number');
    }
}
