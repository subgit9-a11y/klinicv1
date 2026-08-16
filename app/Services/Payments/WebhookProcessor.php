<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes incoming payment webhooks with idempotency.
 *
 * Idempotency is enforced via the unique `event_id` column on
 * payment_webhooks. If an event has already been processed, it is
 * skipped. Each webhook is stored (for audit) before processing.
 *
 * Flow:
 *  1. Store the raw payload in payment_webhooks.
 *  2. If event_id already exists, skip (idempotent).
 *  3. Verify the payment server-side (never trust the webhook alone).
 *  4. Record/update the payment and invoice status.
 *  5. Mark the webhook as processed.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly CashfreePaymentProvider $gateway,
        private readonly BillingService $billing,
    ) {}

    /**
     * Process a Cashfree webhook payload.
     *
     * @param array $payload
     * @param string $signature  Raw signature from the webhook header
     * @return array{processed: bool, event_id: ?string, message: string}
     */
    public function process(array $payload, string $signature): array
    {
        $eventId = $payload['data']['order']['order_id'] ?? $payload['order_id'] ?? null;
        $eventType = $payload['type'] ?? $payload['event'] ?? 'PAYMENT_STATUS';
        $gatewayOrderId = $payload['data']['order']['order_id']
            ?? $payload['data']['payment']['order_id']
            ?? $payload['order_id']
            ?? null;
        $gatewayPaymentId = $payload['data']['payment']['cf_payment_id']
            ?? $payload['data']['payment']['payment_id']
            ?? $payload['payment_id']
            ?? null;

        if ($eventId === null) {
            return ['processed' => false, 'event_id' => null, 'message' => 'Missing event/order ID'];
        }

        // Idempotency check: if this event was already processed, skip.
        $existing = PaymentWebhook::where('event_id', $eventId)->first();
        if ($existing && $existing->processed) {
            return ['processed' => false, 'event_id' => $eventId, 'message' => 'Duplicate event already processed'];
        }

        // Store the webhook (audit trail).
        $webhook = PaymentWebhook::updateOrCreate(
            ['event_id' => $eventId],
            [
                'gateway' => 'CASHFREE',
                'event_type' => $eventType,
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $gatewayPaymentId,
                'payload' => $payload,
                'processed' => false,
            ]
        );

        // Verify the signature (if gateway is configured).
        if ($this->gateway->isConfigured() && !$this->gateway->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Cashfree webhook signature verification failed', ['event_id' => $eventId]);
            return ['processed' => false, 'event_id' => $eventId, 'message' => 'Signature verification failed'];
        }

        // Server-side verification — never trust the webhook payload.
        if ($gatewayOrderId === null) {
            return ['processed' => false, 'event_id' => $eventId, 'message' => 'Missing gateway order ID'];
        }

        $verification = $this->gateway->verify($gatewayOrderId);

        if (!$verification['verified']) {
            $webhook->update(['processed' => true, 'processed_at' => now()]);
            return ['processed' => false, 'event_id' => $eventId, 'message' => 'Payment not verified: ' . ($verification['message'] ?? 'unknown')];
        }

        // Find the invoice via the PaymentOrder.
        $order = \App\Models\PaymentOrder::where('gateway_order_id', $gatewayOrderId)->first();

        if ($order === null) {
            Log::warning('Cashfree webhook: no matching PaymentOrder', ['gateway_order_id' => $gatewayOrderId]);
            $webhook->update(['processed' => true, 'processed_at' => now()]);
            return ['processed' => false, 'event_id' => $eventId, 'message' => 'No matching order found'];
        }

        $invoice = $order->payable;

        DB::transaction(function () use ($invoice, $verification, $gatewayPaymentId, $webhook) {
            if ($invoice instanceof Invoice) {
                $this->billing->recordPayment($invoice, [
                    'method' => 'CASHFREE',
                    'gateway' => 'CASHFREE',
                    'amount_cents' => $verification['amount_cents'] ?? $invoice->amount_due_cents,
                    'gateway_payment_id' => $verification['gateway_payment_id'] ?? $gatewayPaymentId,
                    'gateway_order_id' => $verification['gateway_order_id'] ?? null,
                ]);
            }

            $webhook->update(['processed' => true, 'processed_at' => now()]);
        });

        return ['processed' => true, 'event_id' => $eventId, 'message' => 'Webhook processed successfully'];
    }
}
