<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\CashRegisterService;
use App\Services\Billing\ExpenseService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_open_creates_register_with_opening_float_and_credit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $register = app(CashRegisterService::class)->open($user, [
            'name' => 'Front Desk',
            'opening_balance_cents' => 50000,
        ]);

        $this->assertSame('OPEN', $register->status);
        $this->assertSame(50000, (int) $register->opening_balance_cents);
        $this->assertDatabaseHas('cash_register_entries', [
            'cash_register_id' => $register->id,
            'type' => 'CREDIT',
            'amount_cents' => 50000,
            'description' => 'Opening float',
        ]);
    }

    public function test_open_rejects_second_open_register_for_same_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        app(CashRegisterService::class)->open($user, ['opening_balance_cents' => 1000]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CashRegisterService::class)->open($user, ['opening_balance_cents' => 2000]);
    }

    public function test_close_computes_closing_balance_from_ledger_entries(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user, ['opening_balance_cents' => 5000]); // +5000
        // Simulate a payment linked to the register (+1000) and an expense (-500).
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 100000,
            'amount_paid_cents' => 0,
        ]);

        app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 1000,
            'cash_register_id' => $register->id,
            'collected_by' => $user->id,
        ]);

        app(ExpenseService::class)->create([
            'description' => 'Stationery',
            'amount_cents' => 500,
            'payment_method' => 'CASH',
            'cash_register_id' => $register->id,
        ], $user->id);

        $closed = $service->close($register);

        // 5000 (float) + 1000 (payment) - 500 (expense) = 5500
        $this->assertSame('CLOSED', $closed->status);
        $this->assertSame(5500, (int) $closed->closing_balance_cents);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_close_rejects_already_closed_register(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user);
        $service->close($register);

        $this->expectException(\DomainException::class);
        $service->close($register);
    }

    public function test_close_records_actual_balance_and_variance(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user, ['opening_balance_cents' => 5000]);

        // Expected = 5000 (float). Cashier counts only 4800 → short by 200.
        $closed = $service->close($register, 4800);

        $this->assertSame(5000, (int) $closed->closing_balance_cents);
        $this->assertSame(4800, (int) $closed->actual_balance_cents);
        $this->assertSame(-200, (int) $closed->variance_cents);
    }

    public function test_close_without_count_leaves_actual_and_variance_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $closed = $service->close($service->open($user, ['opening_balance_cents' => 5000]));

        $this->assertNull($closed->actual_balance_cents);
        $this->assertNull($closed->variance_cents);
    }

    public function test_close_rejects_negative_counted_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->close($register, -100);
    }

    public function test_post_adjustment_moves_expected_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user, ['opening_balance_cents' => 5000]);

        $service->postAdjustment($register, 'CREDIT', 1000, 'Correction: mis-entered float', $user);
        $service->postAdjustment($register, 'DEBIT', 500, 'Bank deposit from drawer', $user);

        $this->assertSame(5500, $service->balance($register));
        $this->assertDatabaseHas('cash_register_entries', [
            'cash_register_id' => $register->id,
            'type' => 'DEBIT',
            'amount_cents' => 500,
            'description' => 'Adjustment: Bank deposit from drawer',
        ]);
    }

    public function test_post_adjustment_rejects_closed_register(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user);
        $service->close($register);

        $this->expectException(\DomainException::class);
        $service->postAdjustment($register, 'CREDIT', 1000, 'Too late', $user);
    }

    public function test_gateway_payment_refund_calls_gateway_and_records_refund_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'total_cents' => 49900,
            'amount_due_cents' => 49900,
            'amount_paid_cents' => 0,
        ]);

        $payment = app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASHFREE',
            'gateway' => 'CASHFREE',
            'gateway_payment_id' => 'cf-pay-ref-001',
            'gateway_order_id' => 'gw-ref-001',
            'amount_cents' => 49900,
        ]);

        $calls = [];
        app()->bind(\App\Contracts\PaymentGatewayInterface::class, function () use (&$calls) {
            return new class($calls) implements \App\Contracts\PaymentGatewayInterface {
                public function __construct(private array &$calls) {}

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
                    return ['success' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'message' => 'unused'];
                }

                public function verify(string $gatewayOrderId): array
                {
                    return ['success' => false, 'verified' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'amount_cents' => null, 'message' => 'unused'];
                }

                public function refund(string $gatewayPaymentId, int $amountCents, ?string $reason = null): array
                {
                    $this->calls[] = [$gatewayPaymentId, $amountCents, $reason];

                    return ['success' => true, 'refund_id' => 'cf-refund-001', 'message' => 'Refund processed'];
                }
            };
        });

        $refund = app(BillingService::class)->refund($payment, [
            'amount_cents' => 49900,
            'reason' => 'Patient cancelled',
        ]);

        $this->assertSame([['cf-pay-ref-001', 49900, 'Patient cancelled']], $calls);
        $this->assertSame('cf-refund-001', $refund->gateway_refund_id);
        $this->assertSame('REFUNDED', $invoice->fresh()->status);
    }

    public function test_gateway_payment_refund_fails_closed_when_gateway_errors(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'total_cents' => 10000,
            'amount_due_cents' => 10000,
            'amount_paid_cents' => 0,
        ]);

        $payment = app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASHFREE',
            'gateway' => 'CASHFREE',
            'gateway_payment_id' => 'cf-pay-ref-002',
            'amount_cents' => 10000,
        ]);

        app()->bind(\App\Contracts\PaymentGatewayInterface::class, fn () => new class implements \App\Contracts\PaymentGatewayInterface {
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
                return ['success' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'message' => 'unused'];
            }

            public function verify(string $gatewayOrderId): array
            {
                return ['success' => false, 'verified' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'amount_cents' => null, 'message' => 'unused'];
            }

            public function refund(string $gatewayPaymentId, int $amountCents, ?string $reason = null): array
            {
                return ['success' => false, 'refund_id' => null, 'message' => 'Gateway exception'];
            }
        });

        try {
            app(BillingService::class)->refund($payment, ['amount_cents' => 10000]);
            $this->fail('Expected DomainException for failed gateway refund');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Gateway refund failed', $e->getMessage());
        }

        // Fail closed: NO local refund, invoice untouched.
        $this->assertDatabaseMissing('refunds', ['payment_id' => $payment->id]);
        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_manual_payment_refund_stays_local(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'total_cents' => 2000,
            'amount_due_cents' => 2000,
            'amount_paid_cents' => 0,
        ]);

        $payment = app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 2000,
        ]);

        $refund = app(BillingService::class)->refund($payment, ['amount_cents' => 2000]);

        $this->assertSame('SUCCESS', $refund->status);
        $this->assertNull($refund->gateway_refund_id);
    }

    public function test_payment_without_register_does_not_post_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'total_cents' => 50000,
            'amount_due_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);

        app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 1000,
            'collected_by' => $user->id,
            // no cash_register_id
        ]);

        $this->assertDatabaseMissing('cash_register_entries', [
            'description' => 'Payment '.Invoice::first()->payments()->first()->payment_number,
        ]);
    }

    public function test_refund_posts_debit_entry_on_linked_register(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(CashRegisterService::class);
        $register = $service->open($user, ['opening_balance_cents' => 10000]);

        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'total_cents' => 5000,
            'amount_due_cents' => 5000,
            'amount_paid_cents' => 0,
        ]);

        $billing = app(BillingService::class);
        $payment = $billing->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 5000,
            'cash_register_id' => $register->id,
            'collected_by' => $user->id,
        ]);

        $refund = $billing->refund($payment, [
            'amount_cents' => 2000,
            'reason' => 'Overcharged',
        ]);

        $this->assertDatabaseHas('cash_register_entries', [
            'cash_register_id' => $register->id,
            'type' => 'DEBIT',
            'amount_cents' => 2000,
            'reference_id' => $refund->id,
        ]);
    }

    public function test_expense_rejects_linking_to_closed_register(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $service = app(CashRegisterService::class);
        $register = $service->open($user);
        $service->close($register);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ExpenseService::class)->create([
            'description' => 'Rent',
            'amount_cents' => 10000,
            'cash_register_id' => $register->id,
        ], $user->id);
    }

    public function test_expense_total_for_period_sums_within_date_range(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $service = app(ExpenseService::class);
        $service->create(['description' => 'Rent', 'amount_cents' => 20000, 'expense_date' => '2026-08-10'], $user->id);
        $service->create(['description' => 'Supplies', 'amount_cents' => 5000, 'expense_date' => '2026-08-12'], $user->id);
        $service->create(['description' => 'Old', 'amount_cents' => 3000, 'expense_date' => '2026-07-30'], $user->id);

        $total = $service->totalForPeriod(
            \Carbon\Carbon::parse('2026-08-01'),
            \Carbon\Carbon::parse('2026-08-31'),
        );

        $this->assertSame(25000, $total);
    }
}
