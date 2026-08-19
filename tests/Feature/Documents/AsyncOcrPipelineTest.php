<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Contracts\OCRProviderInterface;
use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocumentOcr;
use App\Models\Document;
use App\Models\Tenant;
use App\Services\Documents\DocumentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\FakeOcrProvider;
use Tests\TestCase;

/**
 * Verifies the async OCR pipeline: upload → DocumentUploaded event →
 * ProcessDocumentOcr job → OcrService extraction — off the request path,
 * gated on an OCR provider being configured.
 */
class AsyncOcrPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function uploadDocument(Tenant $tenant): Document
    {
        app(TenantContext::class)->set($tenant->id);
        $file = UploadedFile::fake()->create('lab-report.pdf', 256, 'application/pdf');

        return app(DocumentService::class)->upload($file, 'LAB_REPORT');
    }

    public function test_upload_dispatches_ocr_job_when_provider_configured(): void
    {
        Queue::fake();
        app()->bind(OCRProviderInterface::class, fn () => new FakeOcrProvider);

        $document = $this->uploadDocument(Tenant::factory()->create());

        Queue::assertPushed(ProcessDocumentOcr::class, fn ($job) => $job->documentId === $document->id);
    }

    public function test_upload_does_not_dispatch_ocr_job_when_provider_unconfigured(): void
    {
        Queue::fake();
        // Default binding: no OCR creds → not configured.
        config(['services.ocr_provider' => 'google_vision', 'services.google_vision.api_key' => null]);

        $this->uploadDocument(Tenant::factory()->create());

        Queue::assertNotPushed(ProcessDocumentOcr::class);
    }

    public function test_job_runs_extraction_and_stamps_metadata(): void
    {
        // Real event + real job (sync queue): the full pipeline executes.
        $provider = new FakeOcrProvider;
        app()->instance(OCRProviderInterface::class, $provider);

        $document = $this->uploadDocument(Tenant::factory()->create());

        $this->assertTrue($provider->called);
        $metadata = $document->fresh()->metadata;
        $this->assertSame('Haemoglobin: 14.2 g/dL', $metadata['ocr_text']);
        $this->assertSame('FAKE_OCR', $metadata['ocr_provider']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.ocr_extracted']);
    }

    public function test_job_restores_tenant_context_from_document(): void
    {
        $provider = new FakeOcrProvider;
        app()->instance(OCRProviderInterface::class, $provider);

        $tenant = Tenant::factory()->create();
        $document = $this->uploadDocument($tenant);

        // Simulate a queue worker: no request tenant context.
        app(TenantContext::class)->forget();

        (new ProcessDocumentOcr($document->id))->handle(app(\App\Services\Documents\OcrService::class));

        $this->assertSame('Haemoglobin: 14.2 g/dL', $document->fresh()->metadata['ocr_text']);
        // Context is cleaned up afterwards so a long-running worker never
        // leaks one tenant's scope into the next job.
        $this->assertFalse(app(TenantContext::class)->isSet());
    }

    public function test_job_ignores_missing_document(): void
    {
        // A deleted document must not fail the job (retrying would be futile).
        (new ProcessDocumentOcr(999999))->handle(app(\App\Services\Documents\OcrService::class));

        $this->assertTrue(true);
    }
}
