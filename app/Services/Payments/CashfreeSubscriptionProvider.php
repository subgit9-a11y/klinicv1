<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\SubscriptionProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cashfree subscription gateway for SaaS plan billing.
 */
class CashfreeSubscriptionProvider implements SubscriptionProviderInterface
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

    public function createSubscription(
        string $internalSubscriptionId,
        int $amountCents,
        string $currency,
        string $customerEmail,
        string $customerPhone,
        array $metadata = []
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'gateway_subscription_id' => null, 'message' => 'Cashfree not configured'];
        }

        $amount = number_format($amountCents / 100, 2, '.', '');

        $payload = [
            'subscription_id' => $internalSubscriptionId,
            'subscription_amount' => $amount,
            'subscription_currency' => $currency,
            'customer_details' => [
                'customer_id' => $metadata['customer_id'] ?? $customerPhone,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
            ],
            'plan' => $metadata['plan_id'] ?? null,
            'frequency' => $metadata['frequency'] ?? 'MONTHLY',
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post("{$this->baseUrl}/subscriptions", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'gateway_subscription_id' => $data['subscription_id'] ?? $internalSubscriptionId,
                    'message' => 'Subscription created',
                ];
            }

            Log::warning('Cashfree createSubscription failed', ['status' => $response->status()]);

            return ['success' => false, 'gateway_subscription_id' => null, 'message' => 'Gateway error'];
        } catch (\Throwable $e) {
            Log::error('Cashfree createSubscription exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'gateway_subscription_id' => null, 'message' => 'Gateway exception'];
        }
    }

    public function cancel(string $gatewaySubscriptionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Cashfree not configured'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post("{$this->baseUrl}/subscriptions/{$gatewaySubscriptionId}/cancel");

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Subscription cancelled'];
            }

            return ['success' => false, 'message' => 'Cancel failed: ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error('Cashfree cancel exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Gateway exception'];
        }
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
