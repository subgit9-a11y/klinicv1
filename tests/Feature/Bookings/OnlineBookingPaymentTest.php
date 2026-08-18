<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Bookings\OnlineBookingService;
use App\Services\Payments\WebhookProcessor;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the public online-booking → payment → confirmation flow:
 *  - Booking leaves the appointment SCHEDULED (never confirmed on the request).
 *  - A Cashfree order + issued invoice are created and linked (PaymentOrder → Invoice).
 *  - The webhook verifies the payment server-side, records it, and only THEN
 *    promotes the appointment to CONFIRMED.
 *  - Cross-tenant / inactive / non-practitioner doctor ids are rejected.
 *  - When the gateway is unconfigured, the flow degrades gracefully (no payment).
 */
class OnlineBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\SystemSettingsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();
        // The public flow uses the first tenant user as the booking creator.
        User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();

        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_gateway_unconfigured_booking_creates_appointment_without_payment(): void
    {
        config([
            'services.cashfree.app_id' => null,
            'services.cashfree.secret_key' => null,
        ]);

        $result = app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9333333333',
            'user_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
        ]);

        $this->assertFalse($result['gateway_configured']);
        $this->assertNull($result['invoice']);
        $this->assertNull($result['payment_url']);
        $this->assertSame('SCHEDULED', $result['appointment']->status);
        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'type' => 'ONLINE',
            'status' => 'SCHEDULED',
        ]);
        $this->assertDatabaseMissing('invoices', ['patient_id' => $result['appointment']->patient_id]);
    }

    public function test_gateway_configured_booking_creates_invoice_and_order_leaves_appointment_scheduled(): void
    {
        $this->swapGatewayWithFake('K360-ORD-OK-001');

        $result = app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9444444444',
            'user_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
        ]);

        $this->assertTrue($result['gateway_configured']);
        $this->assertNotNull($result['invoice']);
        $this->assertNotNull($result['payment_url']);
        $this->assertSame('ISSUED', $result['invoice']->status);
        $this->assertSame('SCHEDULED', $result['appointment']->status);

        $this->assertDatabaseHas('payment_orders', [
            'gateway_order_id' => 'K360-ORD-OK-001',
            'payable_type' => Invoice::class,
            'payable_id' => $result['invoice']->id,
            'status' => 'CREATED',
        ]);
    }

    public function test_webhook_confirms_appointment_only_after_verified_payment(): void
    {
        $this->swapGatewayWithFake('K360-ORD-OK-002', verifyAmount: null);

        $result = app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9555555555',
            'user_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '12:00',
        ]);
        $appointment = $result['appointment'];
        $invoice = $result['invoice'];
        $this->assertSame('SCHEDULED', $appointment->status);

        // Now the webhook arrives: server-side verify returns PAID for the full
        // amount. The WebhookProcessor uses the concrete CashfreePaymentProvider
        // for signature + verify(), so swap that binding too.
        $this->swapCashfreeProvider('K360-ORD-OK-002', (int) $invoice->total_cents);

        $payload = [
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => [
                'order' => ['order_id' => 'K360-ORD-OK-002'],
                'payment' => ['cf_payment_id' => 'cf-pay-002'],
            ],
        ];

        $outcome = app(WebhookProcessor::class)->process($payload, 'valid-sig');

        $this->assertTrue($outcome['processed']);
        $this->assertSame('CONFIRMED', $appointment->fresh()->status);
        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'gateway' => 'CASHFREE',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_webhook_does_not_confirm_when_payment_not_verified(): void
    {
        $this->swapGatewayWithFake('K360-ORD-OK-003', verifyAmount: null, verified: false);

        $result = app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9666666666',
            'user_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
        ]);
        $appointment = $result['appointment'];

        // verify() returns not-verified → no payment recorded → appointment stays SCHEDULED.
        $this->swapCashfreeProvider('K360-ORD-OK-003', null, verified: false);

        $payload = [
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => [
                'order' => ['order_id' => 'K360-ORD-OK-003'],
                'payment' => ['cf_payment_id' => 'cf-pay-003'],
            ],
        ];

        $outcome = app(WebhookProcessor::class)->process($payload, 'sig');

        $this->assertFalse($outcome['processed']);
        // Appointment stays SCHEDULED — payment was NOT verified.
        $this->assertSame('SCHEDULED', $appointment->fresh()->status);
    }

    public function test_cross_tenant_doctor_id_is_rejected(): void
    {
        $this->swapGatewayWithFake('K360-ORD-OK-004');
        $otherTenant = Tenant::factory()->create();
        $otherDoctor = User::factory()->forTenant($otherTenant)->role('DOCTOR')->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9777777777',
            'user_id' => $otherDoctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
        ]);

        $this->assertDatabaseMissing('appointments', ['user_id' => $otherDoctor->id]);
        $this->assertDatabaseMissing('payment_orders', ['gateway_order_id' => 'K360-ORD-OK-004']);
    }

    public function test_inactive_doctor_is_rejected(): void
    {
        $this->swapGatewayWithFake('K360-ORD-OK-005');
        $inactive = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create(['is_active' => false]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9888888888',
            'user_id' => $inactive->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '15:00',
        ]);
    }

    public function test_non_practitioner_role_is_rejected(): void
    {
        $this->swapGatewayWithFake('K360-ORD-OK-006');
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(OnlineBookingService::class)->book($this->tenant, [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9999999999',
            'user_id' => $receptionist->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '16:00',
        ]);
    }

    /**
     * Swap the bound CashfreePaymentProvider concrete (used by WebhookProcessor
     * for signature verification + server-side verify()) with a fake that
     * returns the given gateway order id, an optional verified amount, and a
     * permissive signature check so the webhook test can drive the happy path.
     */
    private function swapCashfreeProvider(string $gatewayOrderId, ?int $verifyAmount, bool $verified = true): void
    {
        app()->bind(\App\Services\Payments\CashfreePaymentProvider::class, function () use ($gatewayOrderId, $verifyAmount, $verified) {
            return new class($gatewayOrderId, $verifyAmount, $verified) extends \App\Services\Payments\CashfreePaymentProvider {
                public function __construct(
                    private readonly string $fakeOrderId,
                    private readonly ?int $fakeAmount,
                    private readonly bool $fakeVerified,
                ) {
                    parent::__construct();
                }

                public function isConfigured(): bool
                {
                    return true;
                }

                public function verify(string $gatewayOrderId): array
                {
                    return [
                        'success' => true,
                        'verified' => $this->fakeVerified,
                        'gateway_order_id' => $gatewayOrderId,
                        'gateway_payment_id' => 'cf-pay-'.$gatewayOrderId,
                        'amount_cents' => $this->fakeAmount,
                        'message' => $this->fakeVerified ? 'PAID' : 'PENDING',
                    ];
                }

                public function verifyWebhookSignature(array $payload, string $signature): bool
                {
                    return true;
                }
            };
        });
    }

    /**
     * Swap the bound gateway with a fake that returns configured + a known
     * gateway_order_id on createOrder, and (optionally) verified on verify().
     */
    private function swapGatewayWithFake(string $gatewayOrderId, ?int $verifyAmount = null, bool $verified = true): void
    {
        app()->bind(PaymentGatewayInterface::class, function () use ($gatewayOrderId, $verifyAmount, $verified) {
            return new class($gatewayOrderId, $verifyAmount, $verified) implements PaymentGatewayInterface {
                public function __construct(
                    private readonly string $gatewayOrderId,
                    private readonly ?int $verifyAmount,
                    private readonly bool $verified,
                ) {}

                public function isConfigured(): bool
                {
                    return true;
                }

                public function name(): string
                {
                    return 'CASHFREE';
                }

                public function createOrder(string $internalOrderId, int $amountCents, string $currency, string $customerEmail, string $customerPhone, array $metadata = []): array
                {
                    return [
                        'success' => true,
                        'gateway_order_id' => $this->gatewayOrderId,
                        'gateway_payment_id' => null,
                        'message' => 'Order created',
                    ];
                }

                public function verify(string $gatewayOrderId): array
                {
                    return [
                        'success' => true,
                        'verified' => $this->verified,
                        'gateway_order_id' => $gatewayOrderId,
                        'gateway_payment_id' => 'cf-pay-'.$gatewayOrderId,
                        'amount_cents' => $this->verifyAmount,
                        'message' => $this->verified ? 'PAID' : 'PENDING',
                    ];
                }

                public function refund(string $gatewayPaymentId, int $amountCents, ?string $reason = null): array
                {
                    return ['success' => true, 'gateway_refund_id' => 'cf-ref-'.$gatewayPaymentId, 'message' => 'Refunded'];
                }
            };
        });
    }
}
