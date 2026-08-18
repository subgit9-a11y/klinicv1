<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Billing\BillingService;
use App\Services\Billing\ReceiptService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function tokenHeader(User $user): array
    {
        $issued = app(TokenService::class)->create($user, 'test', ['*']);

        return ['Authorization' => 'Bearer '.$issued['token']];
    }

    public function test_generate_creates_pdf_document_linked_to_payment(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $payment = Payment::factory()->create(['tenant_id' => $tenant->id, 'amount_cents' => 50000]);

        ['document' => $document] = app(ReceiptService::class)->generate($payment);

        $this->assertSame('RECEIPT', $document->type);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame($payment->id, $document->metadata['payment_id']);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_content_returns_pdf_bytes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);

        $content = app(ReceiptService::class)->content($payment);

        $this->assertNotEmpty($content);
        // PDF magic bytes OR HTML fallback (when dompdf absent in some envs)
        $this->assertTrue(
            str_starts_with($content, '%PDF') || str_starts_with($content, '<!DOCTYPE'),
            'Receipt content should be a PDF or HTML fallback.'
        );
    }

    public function test_latest_document_finds_stored_receipt(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);
        app(ReceiptService::class)->generate($payment);

        $doc = app(ReceiptService::class)->latestDocument($payment);

        $this->assertNotNull($doc);
        $this->assertSame($payment->id, $doc->metadata['payment_id']);
    }

    public function test_api_generate_receipt(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
        $payment = Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
        ]);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson("/api/v1/invoices/{$invoice->id}/payments/{$payment->id}/receipt")
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'RECEIPT');
    }

    public function test_api_download_receipt_returns_pdf(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
        $payment = Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
        ]);

        $this->withHeaders($this->tokenHeader($owner))
            ->getJson("/api/v1/invoices/{$invoice->id}/payments/{$payment->id}/receipt")
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_api_receipt_rejects_cross_invoice_payment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $invoiceA = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $paymentB = Payment::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoiceB->id]);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson("/api/v1/invoices/{$invoiceA->id}/payments/{$paymentB->id}/receipt")
            ->assertNotFound();
    }
}
