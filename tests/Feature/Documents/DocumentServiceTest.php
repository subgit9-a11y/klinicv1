<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Contracts\StorageProviderInterface;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Tenant;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\Storage\S3StorageProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_storage_provider_is_configured(): void
    {
        $this->assertTrue(app(S3StorageProvider::class)->isConfigured());
    }

    public function test_storage_interface_resolves_to_s3(): void
    {
        $this->assertInstanceOf(S3StorageProvider::class, app(StorageProviderInterface::class));
    }

    public function test_storage_store_and_exists_roundtrip(): void
    {
        $provider = app(S3StorageProvider::class);
        $path = 'test/' . uniqid() . '.txt';

        $stored = $provider->store('test content', $path, 'text/plain');

        $this->assertSame($path, $stored);
        $this->assertTrue($provider->exists($path));

        // Cleanup.
        $provider->delete($path);
    }

    public function test_storage_delete_removes_file(): void
    {
        $provider = app(S3StorageProvider::class);
        $path = 'test/' . uniqid() . '.txt';
        $provider->store('content', $path);

        $deleted = $provider->delete($path);

        $this->assertTrue($deleted);
        $this->assertFalse($provider->exists($path));
    }

    public function test_document_service_upload_creates_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $file = UploadedFile::fake()->create('report.pdf', 1024, 'application/pdf');

        $document = app(DocumentService::class)->upload(
            $file,
            'LAB_REPORT',
            $patient,
            auth()->id()
        );

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame('LAB_REPORT', $document->type);
        $this->assertSame('report.pdf', $document->name);
        $this->assertSame($patient->id, $document->patient_id);
        $this->assertSame($tenant->id, $document->tenant_id);
        $this->assertNotNull($document->path);
        $this->assertTrue(app(S3StorageProvider::class)->exists($document->path));

        // Cleanup.
        app(S3StorageProvider::class)->delete($document->path);
    }

    public function test_document_service_upload_with_metadata(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $file = UploadedFile::fake()->create('scan.jpg', 512, 'image/jpeg');

        $document = app(DocumentService::class)->upload(
            $file,
            'IMAGING',
            $patient,
            null,
            ['source' => 'lab_partner', 'report_date' => '2026-08-12']
        );

        $this->assertSame('IMAGING', $document->type);
        $this->assertArrayHasKey('source', $document->metadata);
        $this->assertSame('lab_partner', $document->metadata['source']);

        app(S3StorageProvider::class)->delete($document->path);
    }

    public function test_document_service_delete_removes_file_and_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $file = UploadedFile::fake()->create('doc.pdf', 256, 'application/pdf');
        $document = app(DocumentService::class)->upload($file, 'CLINICAL', $patient);

        $deleted = app(DocumentService::class)->delete($document);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_document_service_generates_temporary_url(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $file = UploadedFile::fake()->create('doc.pdf', 256, 'application/pdf');
        $document = app(DocumentService::class)->upload($file, 'CLINICAL', $patient);

        $url = app(DocumentService::class)->temporaryUrl($document, now()->addMinutes(15));

        $this->assertNotEmpty($url);

        app(S3StorageProvider::class)->delete($document->path);
    }

    public function test_document_factory_creates_valid_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $document = Document::factory()->create();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_pdf_service_store_returns_path(): void
    {
        $path = app(PdfService::class)->store('PDF content', 'test.pdf');

        $this->assertNotEmpty($path);
        $this->assertStringContainsString('test.pdf', $path);

        // Cleanup.
        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
    }

    public function test_pdf_service_generates_actual_pdf_binary_for_prescription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $prescription = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);

        $output = app(PdfService::class)->generatePrescription($prescription);

        // A real PDF starts with the %PDF magic header; HTML fallback would start with "<!DOCTYPE".
        $this->assertStringStartsWith('%PDF', $output, 'PdfService must emit a real PDF, not HTML fallback.');
    }

    public function test_pdf_service_generates_actual_pdf_binary_for_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);

        $output = app(PdfService::class)->generateInvoice($invoice);

        $this->assertStringStartsWith('%PDF', $output, 'PdfService must emit a real PDF, not HTML fallback.');
    }
}
