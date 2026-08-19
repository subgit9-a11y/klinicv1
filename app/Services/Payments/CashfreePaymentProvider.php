<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cashfree payment gateway implementation.
 *
 * Uses the Cashfree PG API (v2 for orders/payments). Real HTTP calls
 * are made only when configured with valid credentials; otherwise the
 * provider gracefully reports "not configured" so the app boots and
 * tests run without live keys.
 *
 * Security: payment success is NEVER trusted from the browser. Every
 * redirect/webhook is verified server-side via the verify() call before
 * recording a SUCCESS payment.
 */
class CashfreePaymentProvider implements PaymentGatewayInterface
{
    private readonly string $baseUrl;

    private readonly string $appId;

    private readonly string $secretKey;

    public function __construct()
    {
        $this->appId = (string) config('services.cashfree.app_id', '');
        $this->secretKey = (string) config('services.cashfree.secret_key', '');
        $this->baseUrl = (string) config(
            'services.cashfree.base_url',
            'https://api.cashfree.com/pg'
        );
    }

    public function name(): string
    {
        return 'CASHFREE';
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->secretKey !== '';
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{success: bool, gateway_order_id: ?string, gateway_payment_id: ?string, checkout_url: ?string, message: string}
     */
    public function createOrder(
        string $internalOrderId,
        int $amountCents,
        string $currency,
        string $customerEmail,
        string $customerPhone,
        array $metadata = []
    ): array {
        if (! $this->isConfigured()) {
            return ['success' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'checkout_url' => null, 'message' => 'Cashfree not configured'];
        }

        $amount = number_format($amountCents / 100, 2, '.', '');

        $payload = [
            'order_id' => $internalOrderId,
            'order_amount' => $amount,
            'order_currency' => $currency,
            'customer_details' => [
                'customer_id' => $metadata['customer_id'] ?? $customerPhone,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
            ],
            'order_meta' => [
                'return_url' => $metadata['return_url'] ?? null,
                'notify_url' => $metadata['notify_url'] ?? null,
            ],
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post("{$this->baseUrl}/orders", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'gateway_order_id' => $data['order_id'] ?? $internalOrderId,
                    'gateway_payment_id' => $data['cf_payment_id'] ?? null,
                    // Only the URL Cashfree actually returned — never
                    // constructed from the order id. If absent the caller
                    // keeps the booking payment-pending instead of sending
                    // the patient to a fabricated link.
                    'checkout_url' => $data['payment_checkout_url']
                        ?? $data['payment_links']['web']
                        ?? $data['checkout_url']
                        ?? null,
                    'message' => 'Order created',
                ];
            }

            Log::warning('Cashfree createOrder failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['success' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'checkout_url' => null, 'message' => 'Gateway error: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('Cashfree createOrder exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'checkout_url' => null, 'message' => 'Gateway exception'];
        }
    }

    /**
     * @return array{success: bool, verified: bool, gateway_order_id: ?string, gateway_payment_id: ?string, amount_cents: ?int, message: string}
     */
    public function verify(string $gatewayOrderId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'verified' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'amount_cents' => null, 'message' => 'Cashfree not configured'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->get("{$this->baseUrl}/orders/{$gatewayOrderId}/payments");

            if (! $response->successful()) {
                return ['success' => false, 'verified' => false, 'gateway_order_id' => $gatewayOrderId, 'gateway_payment_id' => null, 'amount_cents' => null, 'message' => 'Verification lookup failed'];
            }

            $payments = $response->json();

            // An order may carry MULTIPLE payment attempts (attempt 1 FAILED,
            // attempt 2 SUCCESS). Selecting payments[0] by index can treat a
            // successful order as unpaid depending on response ordering — find
            // the attempt whose status is SUCCESS and use THAT attempt's
            // cf_payment_id / amount for settlement.
            $attempts = is_array($payments) ? array_values($payments) : [];
            $payment = null;
            foreach ($attempts as $attempt) {
                $attemptStatus = strtoupper((string) ($attempt['payment_status'] ?? $attempt['order_status'] ?? ''));
                if ($attemptStatus === 'SUCCESS') {
                    $payment = $attempt;
                    break;
                }
            }
            // No successful attempt: report the first attempt's status.
            $payment ??= $attempts[0] ?? null;
            $payment ??= is_array($payments) ? $payments : [];

            $status = $payment['payment_status'] ?? $payment['order_status'] ?? 'UNKNOWN';
            $verified = strtoupper($status) === 'SUCCESS';

            $amountCents = isset($payment['order_amount'])
                ? (int) round(((float) $payment['order_amount']) * 100)
                : null;

            return [
                'success' => true,
                'verified' => $verified,
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $payment['cf_payment_id'] ?? $payment['payment_id'] ?? null,
                'amount_cents' => $amountCents,
                'message' => $verified ? 'Payment verified' : "Payment status: {$status}",
            ];
        } catch (\Throwable $e) {
            Log::error('Cashfree verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'verified' => false, 'gateway_order_id' => $gatewayOrderId, 'gateway_payment_id' => null, 'amount_cents' => null, 'message' => 'Gateway exception'];
        }
    }

    /**
     * @return array{success: bool, refund_id: ?string, message: string}
     */
    public function refund(string $gatewayPaymentId, int $amountCents, ?string $reason = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'refund_id' => null, 'message' => 'Cashfree not configured'];
        }

        $amount = number_format($amountCents / 100, 2, '.', '');

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post("{$this->baseUrl}/payments/{$gatewayPaymentId}/refund", [
                    'refund_amount' => $amount,
                    'refund_note' => $reason ?? 'Refund',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'refund_id' => $data['cf_refund_id'] ?? null,
                    'message' => 'Refund processed',
                ];
            }

            return ['success' => false, 'refund_id' => null, 'message' => 'Refund failed: '.$response->status()];
        } catch (\Throwable $e) {
            Log::error('Cashfree refund exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'refund_id' => null, 'message' => 'Gateway exception'];
        }
    }

    /**
     * Verify the webhook signature. Cashfree's documented scheme signs the
     * EXACT raw HTTP body, optionally prefixed by the `x-webhook-timestamp`
     * header value:
     *   signature = base64(hmac_sha256(timestamp + rawBody, secretKey))
     *
     * The raw body MUST be `$request->getContent()` — never a re-encoding of
     * the parsed JSON, which changes whitespace/escaping/ordering and can
     * reject legitimate deliveries.
     *
     * @param  string  $rawBody  Raw HTTP request body
     * @param  string  $signature  Value of the `x-webhook-signature` /
     *   legacy `x-cf-signature` header
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, ?string $timestamp = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $expected = $this->generateSignature($rawBody, $timestamp);

        return hash_equals($expected, $signature);
    }

    /**
     * Generate a webhook signature for a raw body (used by tests and
     * internal webhook simulation).
     */
    public function generateSignature(string $rawBody, ?string $timestamp = null): string
    {
        return base64_encode(hash_hmac('sha256', (string) $timestamp.$rawBody, $this->secretKey, true));
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-api-version' => '2022-09-01',
            'x-client-id' => $this->appId,
            'x-client-secret' => $this->secretKey,
        ];
    }
}
