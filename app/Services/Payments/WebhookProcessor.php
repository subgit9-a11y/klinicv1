<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\PaymentOrder;
use App\Models\PaymentWebhook;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Log;

/**
 * Processes incoming payment webhooks with idempotency.
 *
 * Idempotency is enforced via the unique `event_id` column on
 * payment_webhooks. The idempotency key is a composite of
 * (gateway_order_id :: gateway_payment_id :: event_type) so that the
 * distinct events a single order can carry — a failed attempt, a retried
 * success, an order-level status — are each processed independently,
 * while duplicate delivery of the same event is skipped. Each webhook is
 * stored (for audit) before processing.
 *
 * Flow:
 *  1. Store the raw payload in payment_webhooks.
 *  2. If event_id already exists & processed, skip (idempotent).
 *  3. Verify the signature, then the payment server-side (never trust the
 *     webhook alone), including amount consistency vs. the PaymentOrder.
 *  4. Record/update the payment and invoice status, advance order lifecycle.
 *  5. Mark the webhook as processed.
 *
 * Each result carries an HTTP `status` so the controller can answer Cashfree
 * with a semantically correct code instead of a blanket 200:
 *   200 — processed, or a redelivery of an already-processed event
 *   400 — malformed payload (no order id)
 *   401 — signature verification failed (do not trust, not worth retrying as-is)
 *   404 — unknown order (Cashfree retries cover the booking/webhook race)
 *   422 — payment not verified server-side, or amount mismatch
 */
class WebhookProcessor
{
    public function __construct(
        private readonly CashfreePaymentProvider $gateway,
        private readonly PaymentSettlementService $settlement,
        private readonly AuditService $audit,
    ) {}

    /**
     * Process a Cashfree webhook payload.
     *
     * @param  string  $signature  Raw signature from the webhook header
     * @param  string|null  $rawBody  Exact raw HTTP request body (signature
     *   verification input — MUST NOT be a re-encoded JSON parse)
     * @param  string|null  $timestamp  Value of the x-webhook-timestamp header
     * @return array{status: int, processed: bool, event_id: ?string, message: string}
     */
    public function process(array $payload, string $signature, ?string $rawBody = null, ?string $timestamp = null): array
    {
        $eventType = $payload['type'] ?? $payload['event'] ?? 'PAYMENT_STATUS';
        $gatewayOrderId = $payload['data']['order']['order_id']
            ?? $payload['data']['payment']['order_id']
            ?? $payload['order_id']
            ?? null;
        $gatewayPaymentId = $payload['data']['payment']['cf_payment_id']
            ?? $payload['data']['payment']['payment_id']
            ?? $payload['payment_id']
            ?? null;

        if ($gatewayOrderId === null) {
            return ['status' => 400, 'processed' => false, 'event_id' => null, 'message' => 'Missing event/order ID'];
        }

        // Idempotency key: a single Cashfree order can carry multiple distinct
        // payment events — e.g. a failed attempt, then a retried success. Each
        // attempt gets its own cf_payment_id. Keying solely on order_id would
        // mark the order "done" after the first event and silently skip the
        // success. Distinguish events by (order :: payment :: event-type):
        //   - failed attempt (PAY1) → "ORD::PAY1::PAYMENT_FAILED"
        //   - success attempt (PAY2) → "ORD::PAY2::PAYMENT_SUCCESS"
        //   - order-level event (no payment) → "ORD::order::PAYMENT_STATUS"
        // Duplicate delivery of the SAME event keeps the same key → skipped.
        $eventId = $gatewayOrderId.'::'.($gatewayPaymentId ?? 'order').'::'.$eventType;

        // Idempotency check: if this event was already processed, skip.
        $existing = PaymentWebhook::where('event_id', $eventId)->first();
        if ($existing && $existing->processed) {
            return ['status' => 200, 'processed' => false, 'event_id' => $eventId, 'message' => 'Duplicate event already processed'];
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

        // Verify the signature (if gateway is configured). An invalid
        // signature is a hard rejection: not trusted, and redelivery of the
        // same tampered payload would fail again — 401 is honest here. The
        // stored row (processed=false) means a later delivery with a VALID
        // signature still processes.
        if ($this->gateway->isConfigured() && ! $this->gateway->verifyWebhookSignature($rawBody ?? (string) json_encode($payload, JSON_UNESCAPED_SLASHES), $signature, $timestamp)) {
            Log::warning('Cashfree webhook signature verification failed', ['event_id' => $eventId]);

            return ['status' => 401, 'processed' => false, 'event_id' => $eventId, 'message' => 'Signature verification failed'];
        }

        // Server-side verification — never trust the webhook payload.
        $verification = $this->gateway->verify($gatewayOrderId);

        if (! $verification['verified']) {
            // A FAILED payment event advances the order lifecycle; any other
            // non-verified state (order still open at the gateway) leaves it.
            $order = PaymentOrder::where('gateway_order_id', $gatewayOrderId)->first();
            if ($order !== null && str_contains((string) $eventType, 'FAILED') && ! $order->isPaid()) {
                $order->markFailed();
            }
            $webhook->update(['processed' => true, 'processed_at' => now()]);

            return ['status' => 422, 'processed' => false, 'event_id' => $eventId, 'message' => 'Payment not verified: '.($verification['message'] ?? 'unknown')];
        }

        // Find the invoice via the PaymentOrder.
        $order = PaymentOrder::where('gateway_order_id', $gatewayOrderId)->first();

        if ($order === null) {
            Log::warning('Cashfree webhook: no matching PaymentOrder', ['gateway_order_id' => $gatewayOrderId]);
            // Do NOT mark processed: 404 makes Cashfree retry, covering the
            // race where the webhook arrives before the booking transaction
            // that creates the order row commits.
            return ['status' => 404, 'processed' => false, 'event_id' => $eventId, 'message' => 'No matching order found'];
        }

        // Settle: amount-consistency check (gateway amount == order ==
        // invoice) + idempotent payment recording + order → PAID, all in a
        // transaction. A mismatch is a deterministic rejection — mark the
        // webhook processed so redelivery is a safe idempotent skip.
        $result = $this->settlement->settle($order, $verification, $gatewayPaymentId);

        if (! $result['settled']) {
            $webhook->update(['processed' => true, 'processed_at' => now()]);

            return ['status' => 422, 'processed' => false, 'event_id' => $eventId, 'message' => $result['message']];
        }

        $webhook->update(['processed' => true, 'processed_at' => now()]);

        $this->audit->record('payment.webhook', 'billing', ['after' => ['event_id' => $eventId]]);

        return ['status' => 200, 'processed' => true, 'event_id' => $eventId, 'message' => 'Webhook processed successfully'];
    }
}
