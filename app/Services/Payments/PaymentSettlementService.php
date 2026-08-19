<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Services\Audit\AuditService;
use App\Services\Billing\BillingService;
use App\Services\Bookings\OnlineBookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settles a gateway-verified payment order: verifies the paid amount matches
 * the order (and therefore the invoice), records the payment, confirms any
 * linked online booking, and advances the order lifecycle to PAID.
 *
 * Shared by the webhook path (WebhookProcessor) and the scheduled
 * reconciliation command so both enforce the same invariants.
 */
class PaymentSettlementService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly OnlineBookingService $bookings,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array{amount_cents: ?int, gateway_payment_id?: ?string, gateway_order_id?: ?string}  $verification
     * @return array{settled: bool, mismatch: bool, message: string}
     */
    public function settle(PaymentOrder $order, array $verification, ?string $fallbackGatewayPaymentId = null): array
    {
        $verifiedCents = $verification['amount_cents'] ?? null;

        // Amount-consistency: what Cashfree says was paid MUST equal what we
        // asked the customer to pay. A ₹499 invoice paid as ₹49 at the
        // gateway must never settle. This is deterministic — no point in the
        // caller retrying, so it is reported as a hard rejection.
        if ($verifiedCents === null || (int) $verifiedCents !== (int) $order->amount_cents) {
            Log::warning('Payment amount mismatch — refusing to settle', [
                'payment_order_id' => $order->id,
                'expected_cents' => $order->amount_cents,
                'verified_cents' => $verifiedCents,
            ]);
            $this->audit->record('payment.amount_mismatch', 'billing', [
                'after' => [
                    'payment_order_id' => $order->id,
                    'expected_cents' => $order->amount_cents,
                    'verified_cents' => $verifiedCents,
                ],
            ], $order);

            return ['settled' => false, 'mismatch' => true, 'message' => 'Verified amount does not match the payment order'];
        }

        $invoice = $order->payable;

        DB::transaction(function () use ($order, $invoice, $verification, $verifiedCents, $fallbackGatewayPaymentId) {
            if ($invoice instanceof Invoice) {
                $this->billing->recordPayment($invoice, [
                    'method' => 'CASHFREE',
                    'gateway' => $order->gateway,
                    'amount_cents' => (int) $verifiedCents,
                    'gateway_payment_id' => $verification['gateway_payment_id'] ?? $fallbackGatewayPaymentId,
                    'gateway_order_id' => $verification['gateway_order_id'] ?? $order->gateway_order_id,
                ]);

                // Promote the linked appointment to CONFIRMED now that payment
                // is verified server-side. Only applies to online bookings
                // (invoice→appointment link); other invoices are a no-op.
                $this->bookings->confirmOnPayment($invoice->fresh());
            }

            $order->refresh();
            if (! $order->isPaid()) {
                $order->markPaid();
            }
        });

        return ['settled' => true, 'mismatch' => false, 'message' => 'Order settled'];
    }
}
