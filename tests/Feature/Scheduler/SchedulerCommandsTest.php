<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use App\Models\ApiToken;
use App\Models\Appointment;
use App\Models\Followup;
use App\Models\Patient;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TreatmentBooking;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_appointment_reminders_command_runs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
            'appointment_date' => Carbon::now()->addHours(6)->toDateString(),
            'start_time' => Carbon::now()->addHours(6)->format('H:i'),
        ]);

        $exit = Artisan::call('klinic:send-appointment-reminders');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('appointment reminders', Artisan::output());
    }

    public function test_treatment_reminders_command_runs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        TreatmentBooking::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'BOOKED',
            'booking_date' => Carbon::now()->addDay()->toDateString(),
        ]);

        $exit = Artisan::call('klinic:send-treatment-reminders');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('treatment reminders', Artisan::output());
    }

    public function test_followup_reminders_command_runs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Followup::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'PENDING',
            'due_date' => Carbon::today()->toDateString(),
        ]);

        $exit = Artisan::call('klinic:send-followup-reminders');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('follow-up reminders', Artisan::output());
    }

    public function test_retry_notifications_command_runs(): void
    {
        $exit = Artisan::call('klinic:retry-notifications');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('notification deliveries', Artisan::output());
    }

    public function test_check_subscriptions_expires_past_due(): void
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'ACTIVE',
            'ends_at' => Carbon::now()->subDay(),
        ]);

        $exit = Artisan::call('klinic:check-subscriptions');

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('subscriptions', ['status' => 'EXPIRED']);
        $this->assertStringContainsString('Expired 1 subscriptions', Artisan::output());
    }

    public function test_check_subscriptions_leaves_active_ones_alone(): void
    {
        $tenant = Tenant::factory()->create();
        $sub = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'ACTIVE',
            'ends_at' => Carbon::now()->addMonth(),
        ]);

        Artisan::call('klinic:check-subscriptions');

        $this->assertDatabaseHas('subscriptions', ['id' => $sub->id, 'status' => 'ACTIVE']);
    }

    public function test_cleanup_deletes_expired_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        ApiToken::factory()->create([
            'tenant_id' => $tenant->id,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        Artisan::call('klinic:cleanup');

        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_cleanup_keeps_valid_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        ApiToken::factory()->create([
            'tenant_id' => $tenant->id,
            'expires_at' => Carbon::now()->addDay(),
        ]);

        Artisan::call('klinic:cleanup');

        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_reconcile_payments_skips_when_unconfigured(): void
    {
        $exit = Artisan::call('klinic:reconcile-payments');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('not configured', Artisan::output());
    }

    public function test_reconcile_payments_settles_verified_order_and_expires_stale_one(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $billing = app(\App\Services\Billing\BillingService::class);
        $invoice = $billing->createInvoice([
            'patient_id' => $patient->id,
            'source' => 'OPD',
            'currency' => 'INR',
        ]);
        $billing->addInvoiceItem($invoice, [
            'description' => 'Consult',
            'type' => 'CONSULTATION',
            'quantity' => 1,
            'unit_price_cents' => 49900,
            'currency' => 'INR',
        ]);
        $invoice = $billing->issue($invoice);

        // Open order the webhook missed — gateway will verify it PAID.
        $open = \App\Models\PaymentOrder::create([
            'tenant_id' => $tenant->id,
            'internal_order_id' => 'K360-ORD-REC-001',
            'gateway' => 'CASHFREE',
            'gateway_order_id' => 'gw-rec-001',
            'payable_type' => \App\Models\Invoice::class,
            'payable_id' => $invoice->id,
            'amount_cents' => $invoice->total_cents,
            'currency' => 'INR',
            'status' => 'CREATED',
        ]);
        $open->forceFill(['created_at' => Carbon::now()->subHours(2)])->save();

        // Stale order past TTL, gateway says not paid → EXPIRED.
        $stale = \App\Models\PaymentOrder::create([
            'tenant_id' => $tenant->id,
            'internal_order_id' => 'K360-ORD-REC-002',
            'gateway' => 'CASHFREE',
            'gateway_order_id' => 'gw-rec-002',
            'payable_type' => \App\Models\Invoice::class,
            'payable_id' => $invoice->id,
            'amount_cents' => 1000,
            'currency' => 'INR',
            'status' => 'PENDING',
        ]);
        $stale->forceFill(['created_at' => Carbon::now()->subHours(72)])->save();

        // Fake the concrete provider the command resolves: gw-rec-001 verifies,
        // gw-rec-002 does not.
        app()->bind(\App\Services\Payments\CashfreePaymentProvider::class, function () {
            return new class extends \App\Services\Payments\CashfreePaymentProvider {
                public function isConfigured(): bool
                {
                    return true;
                }

                public function verify(string $gatewayOrderId): array
                {
                    if ($gatewayOrderId === 'gw-rec-001') {
                        return ['success' => true, 'verified' => true, 'gateway_order_id' => $gatewayOrderId, 'gateway_payment_id' => 'cf-rec-001', 'amount_cents' => 49900, 'message' => 'PAID'];
                    }

                    return ['success' => false, 'verified' => false, 'gateway_order_id' => $gatewayOrderId, 'gateway_payment_id' => null, 'amount_cents' => null, 'message' => 'NOT_PAID'];
                }
            };
        });

        $exit = Artisan::call('klinic:reconcile-payments');

        $this->assertSame(0, $exit);
        $this->assertSame('PAID', $open->fresh()->status);
        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'status' => 'SUCCESS']);
        $this->assertSame('EXPIRED', $stale->fresh()->status);
    }
}
