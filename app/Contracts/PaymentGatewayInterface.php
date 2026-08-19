<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Create an order at the gateway.
     *
     * `checkout_url` is the EXACT hosted-checkout URL returned by the
     * gateway. It must never be fabricated client-side; when the gateway
     * doesn't supply one, return null and let the caller surface
     * "payment pending" instead of a constructed URL.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{success: bool, gateway_order_id: ?string, gateway_payment_id: ?string, checkout_url: ?string, message: string}
     */
    public function createOrder(string $internalOrderId, int $amountCents, string $currency, string $customerEmail, string $customerPhone, array $metadata = []): array;

    /**
     * Verify a payment server-side. Never trust the browser redirect.
     *
     * @return array{success: bool, verified: bool, gateway_order_id: ?string, gateway_payment_id: ?string, amount_cents: ?int, message: string}
     */
    public function verify(string $gatewayOrderId): array;

    /**
     * Refund a payment.
     *
     * @return array{success: bool, refund_id: ?string, message: string}
     */
    public function refund(string $gatewayPaymentId, int $amountCents, ?string $reason = null): array;
}
