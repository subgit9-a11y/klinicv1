<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SubscriptionProviderInterface;
use App\Models\PaymentWebhook;
use App\Models\Tenant;
use App\Services\Payments\CashfreePaymentProvider;
use App\Services\Payments\CashfreeSubscriptionProvider;
use Illuminate\Support\Facades\Http;
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
        // Signature must be computed over the EXACT raw body (not a re-parsed
        // JSON re-encode) — same string in, same string verified.
        $rawBody = '{"data":{"order":{"order_id":"cf-123"}}}';

        $signature = $provider->generateSignature($rawBody);

        $this->assertTrue($provider->verifyWebhookSignature($rawBody, $signature));
    }

    public function test_webhook_signature_uses_timestamp_when_supplied(): void
    {
        $provider = app(CashfreePaymentProvider::class);
        $rawBody = '{"data":{"order":{"order_id":"cf-123"}}}';
        $timestamp = '1700000000';

        $signature = $provider->generateSignature($rawBody, $timestamp);

        $this->assertTrue($provider->verifyWebhookSignature($rawBody, $signature, $timestamp));
        // Same body without the timestamp must NOT validate against a
        // timestamp-signed delivery.
        $this->assertFalse($provider->verifyWebhookSignature($rawBody, $signature));
    }

    public function test_webhook_signature_rejects_tampered_body(): void
    {
        $provider = app(CashfreePaymentProvider::class);
        $rawBody = '{"data":{"order":{"order_id":"cf-123"}}}';
        $signature = $provider->generateSignature($rawBody);

        $tampered = '{"data":{"order":{"order_id":"cf-999"}}}';
        $this->assertFalse($provider->verifyWebhookSignature($tampered, $signature));
    }

    public function test_webhook_signature_rejects_reencoded_json(): void
    {
        $provider = app(CashfreePaymentProvider::class);
        // A whitespace-different but semantically identical body must fail:
        // guards against accidental re-encoding of the parsed JSON.
        $rawBody = '{"data": {"order": {"order_id":"cf-123"}}}';
        $reEncoded = json_encode(json_decode($rawBody, true), JSON_UNESCAPED_SLASHES);
        $signature = $provider->generateSignature($rawBody);

        $this->assertNotSame($reEncoded, $rawBody);
        $this->assertFalse($provider->verifyWebhookSignature($reEncoded, $signature));
    }

    // --- verify() selects the actual SUCCESS attempt ---

    public function test_verify_selects_success_payment_among_multiple_attempts(): void
    {
        Http::fake([
            'https://api.test.cashfree.com/pg/orders/ORD-1/payments' => Http::response([
                ['payment_status' => 'FAILED', 'cf_payment_id' => 'pay-failed', 'order_amount' => 499.0],
                ['payment_status' => 'SUCCESS', 'cf_payment_id' => 'pay-success', 'order_amount' => 499.0],
            ], 200),
        ]);

        $result = app(CashfreePaymentProvider::class)->verify('ORD-1');

        $this->assertTrue($result['verified']);
        $this->assertSame('pay-success', $result['gateway_payment_id']);
        $this->assertSame(49900, $result['amount_cents']);
    }

    public function test_verify_reports_unverified_when_no_success_attempt(): void
    {
        Http::fake([
            'https://api.test.cashfree.com/pg/orders/ORD-2/payments' => Http::response([
                ['payment_status' => 'FAILED', 'cf_payment_id' => 'pay-failed', 'order_amount' => 499.0],
                ['payment_status' => 'ACTIVE', 'cf_payment_id' => 'pay-active', 'order_amount' => 499.0],
            ], 200),
        ]);

        $result = app(CashfreePaymentProvider::class)->verify('ORD-2');

        $this->assertFalse($result['verified']);
    }

    // --- Webhook processor idempotency ---

    public function test_webhook_processor_skips_duplicate_event(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        // Pre-create an already-processed webhook with the SAME composite
        // idempotency key the processor will compute for this payload.
        // Key = "{order}::{payment_id ?? 'order'}::{event_type}".
        $payload = ['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => ['order' => ['order_id' => 'cf-001']]];
        $compositeKey = 'cf-001::order::PAYMENT_SUCCESS_WEBHOOK';

        PaymentWebhook::factory()->create([
            'event_id' => $compositeKey,
            'processed' => true,
        ]);

        // WebhookProcessor will verify with the gateway (HTTP mocked to fail
        // is fine — duplicate detection happens first).
        $result = app(WebhookProcessor::class)->process($payload, 'invalid-signature');

        $this->assertFalse($result['processed']);
        $this->assertSame('Duplicate event already processed', $result['message']);
    }

    public function test_webhook_processor_processes_failed_then_success_on_same_order(): void
    {
        // Regression for review item #2: a single Cashfree order can carry
        // multiple distinct payment events (a failed attempt, then a retried
        // success). Keying on order_id alone would skip the success after the
        // first event. The composite key (order::payment::type) must let both
        // through.
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        // Failed attempt — gateway unconfigured, so verification returns
        // not-verified and the webhook is stored + marked processed without
        // recording a payment.
        $failedPayload = [
            'type' => 'PAYMENT_FAILED_WEBHOOK',
            'data' => ['order' => ['order_id' => 'ORD-X'], 'payment' => ['cf_payment_id' => 'PAY-1']],
        ];
        $r1 = app(WebhookProcessor::class)->process($failedPayload, 'invalid-signature');
        $this->assertFalse($r1['processed']); // not verified (gateway down) → no payment recorded

        // Success attempt on the SAME order but a DIFFERENT payment id.
        $successPayload = [
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => ['order' => ['order_id' => 'ORD-X'], 'payment' => ['cf_payment_id' => 'PAY-2']],
        ];
        $r2 = app(WebhookProcessor::class)->process($successPayload, 'invalid-signature');

        // The success event must NOT be skipped as a duplicate of the failed
        // event — the two have distinct composite keys.
        $this->assertNotSame('Duplicate event already processed', $r2['message']);
        $this->assertSame('ORD-X::PAY-1::PAYMENT_FAILED_WEBHOOK', $r1['event_id']);
        $this->assertSame('ORD-X::PAY-2::PAYMENT_SUCCESS_WEBHOOK', $r2['event_id']);
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

        // Webhook should be stored for audit (look it up by gateway_order_id
        // rather than the composite event_id, which is an internal detail).
        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_order_id' => 'evt-002',
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

    // --- Route wiring: the public webhook endpoint must delegate to the
    //     WebhookProcessor (not return a stub "Webhook received" body). ---

    public function test_webhook_endpoint_delegates_to_processor_and_stores_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $payload = ['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => ['order' => ['order_id' => 'evt-route-001']]];

        // Signature is invalid → processor stores the webhook but does not
        // mark it processed. The endpoint answers 401 (not a blanket 200) so
        // operational monitoring can alert on signature failures; a later
        // delivery carrying a VALID signature still processes, because the
        // stored row was left unprocessed.
        $response = $this->postJson('/api/v1/webhooks/payments', $payload, [
            'X-Cf-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('processed', false);
        $response->assertJsonPath('message', 'Signature verification failed');

        $this->assertDatabaseHas('payment_webhooks', [
            'gateway_order_id' => 'evt-route-001',
            'processed' => false,
        ]);
    }

    public function test_webhook_endpoint_rejects_missing_event_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $response = $this->postJson('/api/v1/webhooks/payments', [
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
        ], ['X-Cf-Signature' => 'sig']);

        $response->assertStatus(400);
        $response->assertJsonPath('processed', false);
        $response->assertJsonPath('message', 'Missing event/order ID');
    }
}
