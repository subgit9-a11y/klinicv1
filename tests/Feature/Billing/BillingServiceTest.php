<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Services\Billing\BillingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function clinicStaff(Tenant $tenant, string $role = 'CLINIC_OWNER'): User
    {
        return User::factory()->forTenant($tenant)->role($role)->create();
    }

    public function test_create_invoice_creates_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $invoice = app(BillingService::class)->createInvoice([
            'patient_id' => $patient->id,
            'source' => 'OPD',
        ]);

        $this->assertSame('DRAFT', $invoice->status);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertSame('OPD', $invoice->source);
    }

    public function test_add_invoice_item_calculates_total(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'DRAFT']);

        $item = app(BillingService::class)->addInvoiceItem($invoice, [
            'description' => 'Consultation fee',
            'type' => 'CONSULTATION',
            'quantity' => 2,
            'unit_price_cents' => 50000,
            'discount_cents' => 10000,
        ]);

        $this->assertSame(90000, $item->total_cents);
    }

    public function test_add_invoice_item_throws_when_not_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'ISSUED']);

        $this->expectException(\DomainException::class);
        app(BillingService::class)->addInvoiceItem($invoice, [
            'description' => 'Test',
            'unit_price_cents' => 50000,
        ]);
    }

    public function test_issue_calculates_totals_and_sets_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'DRAFT']);

        app(BillingService::class)->addInvoiceItem($invoice, [
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price_cents' => 50000,
        ]);
        app(BillingService::class)->addInvoiceItem($invoice, [
            'description' => 'Item 2',
            'quantity' => 2,
            'unit_price_cents' => 30000,
        ]);

        $issued = app(BillingService::class)->issue($invoice);

        $this->assertSame('ISSUED', $issued->status);
        $this->assertSame(110000, $issued->subtotal_cents);
        $this->assertSame(110000, $issued->total_cents);
        $this->assertSame(110000, $issued->amount_due_cents);
        $this->assertNotNull($issued->issued_at);
    }

    public function test_issue_throws_when_not_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'ISSUED']);

        $this->expectException(\DomainException::class);
        app(BillingService::class)->issue($invoice);
    }

    public function test_record_full_payment_marks_paid(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 100000,
        ]);

        $payment = app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 100000,
        ]);

        $this->assertSame('SUCCESS', $payment->status);
        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame(100000, $invoice->amount_paid_cents);
        $this->assertSame(0, $invoice->amount_due_cents);
    }

    public function test_record_partial_payment_marks_partially_paid(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 100000,
        ]);

        app(BillingService::class)->recordPayment($invoice, [
            'method' => 'UPI',
            'amount_cents' => 40000,
        ]);

        $invoice->refresh();
        $this->assertSame('PARTIALLY_PAID', $invoice->status);
        $this->assertSame(40000, $invoice->amount_paid_cents);
        $this->assertSame(60000, $invoice->amount_due_cents);
    }

    public function test_multiple_partial_payments_lead_to_paid(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 100000,
        ]);

        $svc = app(BillingService::class);
        $svc->recordPayment($invoice, ['method' => 'CASH', 'amount_cents' => 30000]);
        $svc->recordPayment($invoice, ['method' => 'UPI', 'amount_cents' => 30000]);
        $svc->recordPayment($invoice, ['method' => 'CARD', 'amount_cents' => 40000]);

        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_record_payment_throws_on_paid_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'PAID']);

        $this->expectException(\DomainException::class);
        app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 1000,
        ]);
    }

    public function test_record_payment_rejects_overpayment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 60000,
            'amount_paid_cents' => 40000,
        ]);

        // 80,000 would exceed the 60,000 outstanding balance — must be refused
        // so a concurrent or careless payment cannot over-collect.
        $this->expectException(\DomainException::class);
        app(BillingService::class)->recordPayment($invoice, [
            'method' => 'CASH',
            'amount_cents' => 80000,
        ]);
    }

    public function test_record_payment_is_idempotent_on_gateway_payment_id(): void
    {
        // A gateway payment (identified by gateway_payment_id) must be
        // recorded at most once — duplicate webhook delivery or a retry must
        // return the existing payment, not create a second row (review #2/#10).
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 100000,
        ]);

        $svc = app(BillingService::class);
        $first = $svc->recordPayment($invoice, [
            'method' => 'CASHFREE',
            'gateway' => 'CASHFREE',
            'gateway_payment_id' => 'cf-pay-9901',
            'amount_cents' => 100000,
        ]);

        // Duplicate delivery of the same gateway payment.
        $second = $svc->recordPayment($invoice, [
            'method' => 'CASHFREE',
            'gateway' => 'CASHFREE',
            'gateway_payment_id' => 'cf-pay-9901',
            'amount_cents' => 100000,
        ]);

        $this->assertSame($first->id, $second->id, 'Duplicate gateway payment must not be re-recorded');
        $this->assertSame(1, \App\Models\Payment::where('gateway_payment_id', 'cf-pay-9901')->count());
        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame(100000, $invoice->amount_paid_cents);
    }

    public function test_record_payment_allows_distinct_gateway_payments(): void
    {
        // Two genuinely distinct gateway payments (different gateway_payment_id)
        // are both recorded — partial payments against the same invoice.
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'ISSUED',
            'total_cents' => 100000,
            'amount_due_cents' => 100000,
        ]);

        $svc = app(BillingService::class);
        $svc->recordPayment($invoice, [
            'method' => 'CASHFREE', 'gateway' => 'CASHFREE',
            'gateway_payment_id' => 'cf-pay-A', 'amount_cents' => 60000,
        ]);
        $svc->recordPayment($invoice, [
            'method' => 'CASHFREE', 'gateway' => 'CASHFREE',
            'gateway_payment_id' => 'cf-pay-B', 'amount_cents' => 40000,
        ]);

        $this->assertSame(2, \App\Models\Payment::where('invoice_id', $invoice->id)->count());
        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame(100000, $invoice->amount_paid_cents);
    }

    public function test_refund_full_amount_marks_invoice_refunded(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'status' => 'PAID',
            'total_cents' => 100000,
            'amount_paid_cents' => 100000,
        ]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 100000,
        ]);

        $refund = app(BillingService::class)->refund($payment, [
            'amount_cents' => 100000,
            'reason' => 'Service cancelled',
        ]);

        $this->assertSame('SUCCESS', $refund->status);
        $this->assertSame('REFUNDED', $invoice->fresh()->status);
    }

    public function test_partial_refund_does_not_change_invoice_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'PAID']);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount_cents' => 100000,
        ]);

        app(BillingService::class)->refund($payment, [
            'amount_cents' => 30000,
        ]);

        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_refund_throws_when_exceeds_remaining(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $payment = Payment::factory()->create(['amount_cents' => 50000]);

        app(BillingService::class)->refund($payment, ['amount_cents' => 30000]);

        $this->expectException(\DomainException::class);
        app(BillingService::class)->refund($payment, ['amount_cents' => 30000]);
    }

    public function test_void_sets_status_and_timestamp(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'ISSUED']);

        $result = app(BillingService::class)->void($invoice, 'Duplicate');

        $this->assertSame('VOID', $result->status);
        $this->assertNotNull($result->voided_at);
    }

    public function test_void_throws_on_paid_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create(['status' => 'PAID']);

        $this->expectException(\DomainException::class);
        app(BillingService::class)->void($invoice);
    }

    public function test_invoices_for_patient_returns_ordered(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $inv1 = Invoice::factory()->create(['patient_id' => $patient->id]);
        $inv2 = Invoice::factory()->create(['patient_id' => $patient->id]);

        $result = app(BillingService::class)->invoicesForPatient($patient->id);

        $this->assertCount(2, $result);
        $this->assertSame($inv2->id, $result->first()->id);
    }

    // --- Policy ---

    public function test_policy_allows_clinic_owner_to_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = $this->clinicStaff($tenant);

        $this->assertTrue((new InvoicePolicy)->create($user));
    }

    public function test_policy_blocks_cross_tenant_refund(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $invoice = Invoice::factory()->create();

        $this->setTenant($tenantB);
        $userB = $this->clinicStaff($tenantB);

        $this->assertFalse((new InvoicePolicy)->refund($userB, $invoice));
    }

    public function test_policy_allows_super_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create();
        $superAdmin = User::factory()->role('SUPER_ADMIN')->create();

        $this->assertTrue((new InvoicePolicy)->view($superAdmin, $invoice));
        $this->assertTrue((new InvoicePolicy)->refund($superAdmin, $invoice));
    }
}
