<?php

declare(strict_types=1);

namespace App\Contracts;

interface SubscriptionProviderInterface
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Create a subscription at the gateway (e.g. Cashfree subscription).
     *
     * @return array{success: bool, gateway_subscription_id: ?string, message: string}
     */
    public function createSubscription(string $internalSubscriptionId, int $amountCents, string $currency, string $customerEmail, string $customerPhone, array $metadata = []): array;

    /**
     * Cancel a subscription at the gateway.
     *
     * @return array{success: bool, message: string}
     */
    public function cancel(string $gatewaySubscriptionId): array;
}
