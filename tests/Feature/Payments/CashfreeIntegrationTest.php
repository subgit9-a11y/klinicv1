<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SubscriptionProviderInterface;
use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhook;
use App\Models\Tenant;
use App\Services\Payments\CashfreePaymentProvider;
use App\Services\Payments\CashfreeSubscriptionProvider;
use App\Services\Payments\WebhookProcessor;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashfreeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Set test credentials so isConfigured() returns true.
        config([
            'services.cashfree.app_id' => 'test_app_id',
            'services.cashfree.secret_key' => 'test_secret_key',
            'services.cashfree.base_url' => 'https://api.test.cashfree.com/pg',
        ]);
    }

    // --- Provider configuration ---

    public function test_payment_provider_is_configured_with_credentials(): void
    {
        $this->assertTrue(app(CashfreePaymentProvider::class)->isConfigured());
    }

    public function test_payment_provider_not_configured_without_credentials(): void
    {
        config([
            'services.cashfree.app_id' => null,
            'services.cashfree.secret_key' => null,
        ]);

        $this->assertFalse(app(CashfreePaymentProvider::class)->isConfigured());
    }

    public function test_payment_provider_name(): void
    {
        $this->assertSame('CASHFREE', app(CashfreePaymentProvider::class)->name());
    }

    public function test_subscription_provider_is_configured(): void
    {
        $this->assertTrue(app(CashfreeSubscriptionProvider::class)->isConfigured());
    }

    // --- Interface binding ---

    public function test_payment_gateway_interface_resolves_to_cashfree(): void
    {
        $gateway = app(PaymentGatewayInterface::class);

        $this->assertInstanceOf(CashfreePaymentProvider::class, $gateway);
    }

    public function test_subscription_provider_interface_resolves_to_cashfree(): void
    {
        $provider = app(SubscriptionProviderInterface::class);

        $this->assertInstanceOf(CashfreeSubscriptionProvider::class, $provider);
    }

    // --- Graceful fallback when not configured ---

    public function test_create_order_returns_not_configured_without_credentials(): void
    {
        config([
            'services.cashfree.app_id' => null,
            'services.cashfree.secret_key' => null,
        ]);

        $result = app(CashfreePaymentProvider::class)->createOrder(
            'K360-ORD-001',
            50000,
            'INR',
            'test@example.com',
            '9999999999'
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Cashfree not configured', $result['message']);
    }

    public function test_verify_returns_not_configured_without_credentials(): void
    {
        config([
            'services.cashfree.app_id' => null,
            'services.cashfree.secret_key' => null,
        ]);

        $result = app(CashfreePaymentProvider::class)->verify('cf-123');

        $this->assertFalse($result['verified']);
    }

    public function test_refund_returns_not_configured_without_credentials(): void
    {
        config([
            'services.cashfree.app_id' => null,
            'services.cashfree.secret_key' => null,
        ]);

        $result = app(CashfreePaymentProvider::class)->refund('pay-123', 10000);

        $this->assertFalse($result['success']);
    }

    // --- Webhook signature ---

    public function test_webhook_signature_roundtrip(): void
    {
        $provider = app(CashfreePaymentProvider::class);
        $payload = ['data' => ['order' => ['order_id' => 'cf-123']]];

        $signature = $provider->generateSignature($payload);

        $this->assertTrue($provider->verifyWebhookSignature($payload, $signature));
    }

    public function test_webhook_signature_rejects_tampered_payload(): void
    {
        $provider = app(CashfreePaymentProvider::class);
        $payload = ['data' => ['order' => ['order_id' => 'cf-123']]];
        $signature = $provider->generateSignature($payload);

        $tampered = ['data' => ['order' => ['order_id' => 'cf-999']]];
        $this->assertFalse($provider->verifyWebhookSignature($tampered, $signature));
    }

    // --- Webhook processor idempotency ---

    public function test_webhook_processor_skips_duplicate_event(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        // Pre-create an already-processed webhook.
        PaymentWebhook::factory()->create([
            'event_id' => 'cf-001',
            'processed' => true,
        ]);

        $payload = ['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => ['order' => ['order_id' => 'cf-001']]];

        // WebhookProcessor will verify with the gateway (HTTP mocked to fail
        // is fine — duplicate detection happens first).
        $result = app(WebhookProcessor::class)->process($payload, 'invalid-signature');

        $this->assertFalse($result['processed']);
        $this->assertSame('Duplicate event already processed', $result['message']);
    }

    public function test_webhook_processor_stores_unverified_webhook(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $payload = ['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => ['order' => ['order_id' => 'evt-002']]];

        // Signature will be invalid → stored but not processed.
        $result = app(WebhookProcessor::class)->process($payload, 'invalid-signature');

        $this->assertFalse($result['processed']);
        $this->assertSame('Signature verification failed', $result['message']);

        // Webhook should be stored for audit.
        $this->assertDatabaseHas('payment_webhooks', [
            'event_id' => 'evt-002',
            'processed' => false,
        ]);
    }

    public function test_webhook_processor_returns_error_for_missing_order_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $payload = ['type' => 'PAYMENT_SUCCESS_WEBHOOK'];

        $result = app(WebhookProcessor::class)->process($payload, 'sig');

        $this->assertFalse($result['processed']);
        $this->assertSame('Missing event/order ID', $result['message']);
    }
}
