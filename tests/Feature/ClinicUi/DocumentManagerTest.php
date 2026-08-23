<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Contracts\OCRProviderInterface;
use App\Livewire\Documents\DocumentManager;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\OcrService;
use App\Services\Storage\S3StorageProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\Fakes\FakeOcrProvider;
use Tests\TestCase;

class DocumentManagerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    private function cleanup(Document $doc): void
    {
        app(S3StorageProvider::class)->delete($doc->path);
    }

    public function test_upload_creates_document_and_audit(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->set('file', UploadedFile::fake()->create('scan.pdf', 128, 'application/pdf'))
            ->set('type', 'LAB_REPORT')
            ->set('patient_id', $patient->id)
            ->call('upload');

        $doc = Document::sole();
        $this->assertSame('LAB_REPORT', $doc->type);
        $this->assertSame($patient->id, $doc->patient_id);
        $this->assertSame(1, $doc->metadata['version']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.uploaded',
            'auditable_id' => $doc->id,
        ]);

        $this->cleanup($doc);
    }

    public function test_recategorize_updates_type_with_audit(): void
    {
        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'consent.pdf', 'type' => 'OTHER', 'disk' => 'LOCAL',
            'path' => 'x/y.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->call('recategorize', $doc->id, 'CONSENT');

        $this->assertSame('CONSENT', $doc->fresh()->type);
        $audit = AuditLog::where('action', 'document.recategorized')->sole();
        $this->assertSame('OTHER', $audit->before['type']);
        $this->assertSame('CONSENT', $audit->after['type']);
    }

    public function test_version_upload_links_versions(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->set('file', UploadedFile::fake()->create('v1.pdf', 64, 'application/pdf'))
            ->set('type', 'CLINICAL')
            ->set('patient_id', $patient->id)
            ->call('upload');

        $v1 = Document::sole();

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->call('openDetail', $v1->id)
            ->set('versionFile', UploadedFile::fake()->create('v2.pdf', 64, 'application/pdf'))
            ->call('uploadNewVersion', $v1->id);

        $this->assertSame(2, Document::count());
        $v2 = Document::where('id', '!=', $v1->id)->sole();
        $this->assertSame(2, $v2->metadata['version']);
        $this->assertSame($v1->id, $v2->metadata['version_of']);
        $this->assertSame($v1->id, $v2->metadata['supersedes']);
        $this->assertSame('CLINICAL', $v2->type);

        $this->cleanup($v1);
        $this->cleanup($v2);
    }

    public function test_archive_hides_from_default_list_and_restore(): void
    {
        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'old-report.pdf', 'type' => 'LAB_REPORT', 'disk' => 'LOCAL',
            'path' => 'x/z.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        Livewire::actingAs($this->owner)->test(DocumentManager::class)
            ->assertSee('old-report.pdf')
            ->call('archive', $doc->id);

        $this->assertNotNull($doc->fresh()->metadata['archived_at']);

        Livewire::actingAs($this->owner)->test(DocumentManager::class)
            ->assertDontSee('old-report.pdf')
            ->set('showArchived', true)
            ->assertSee('old-report.pdf')
            ->call('unarchive', $doc->id);

        $this->assertArrayNotHasKey('archived_at', $doc->fresh()->metadata);
    }

    public function test_share_generates_signed_url_and_audits(): void
    {
        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'shared.pdf', 'type' => 'OTHER', 'disk' => 'LOCAL',
            'path' => 'x/s.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->call('share', $doc->id)
            ->assertSet('shareDocId', $doc->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.shared',
            'auditable_id' => $doc->id,
        ]);
    }

    public function test_delete_removes_document_and_file(): void
    {
        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->set('file', UploadedFile::fake()->create('todelete.pdf', 64, 'application/pdf'))
            ->set('type', 'OTHER')
            ->call('upload');

        $doc = Document::sole();
        $path = $doc->path;

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->call('delete', $doc->id);

        $this->assertSame(0, Document::count());
        $this->assertFalse(app(S3StorageProvider::class)->exists($path));
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.deleted']);
    }

    public function test_therapist_cannot_upload_or_delete(): void
    {
        // THERAPIST has documents.view but not upload/delete.
        $therapist = User::factory()->forTenant($this->tenant)->role('THERAPIST')->create();
        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'keep.pdf', 'type' => 'OTHER', 'disk' => 'LOCAL',
            'path' => 'x/k.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        Livewire::actingAs($therapist)
            ->test(DocumentManager::class)
            ->set('file', UploadedFile::fake()->create('x.pdf', 64, 'application/pdf'))
            ->set('type', 'OTHER')
            ->call('upload')
            ->assertForbidden();

        Livewire::actingAs($therapist)
            ->test(DocumentManager::class)
            ->call('delete', $doc->id)
            ->assertForbidden();

        $this->assertSame(1, Document::count());
    }

    public function test_cross_tenant_document_not_accessible(): void
    {
        $otherTenant = Tenant::factory()->create();
        // BelongsToTenant force-stamps tenant_id from context on create.
        $ctx = app(TenantContext::class);
        $ctx->set($otherTenant->id);
        $foreign = Document::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'foreign.pdf', 'type' => 'OTHER', 'disk' => 'LOCAL',
            'path' => 'x/f.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);
        $ctx->set($this->tenant->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->call('archive', $foreign->id);
    }

    public function test_download_is_audited(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->set('file', UploadedFile::fake()->create('dl.pdf', 64, 'application/pdf'))
            ->set('type', 'OTHER')
            ->set('patient_id', $patient->id)
            ->call('upload');

        $doc = Document::sole();

        $response = $this->actingAs($this->owner)->get(route('documents.show', $doc));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.downloaded',
            'auditable_id' => $doc->id,
        ]);

        $this->cleanup($doc);
    }

    public function test_run_ocr_stamps_text(): void
    {
        app()->forgetInstance(OcrService::class);
        app()->forgetInstance(OCRProviderInterface::class);
        app()->instance(OCRProviderInterface::class, new FakeOcrProvider());

        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ocr.pdf', 'type' => 'LAB_REPORT', 'disk' => 'LOCAL',
            'path' => 'x/o.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        Livewire::actingAs($this->owner)
            ->test(DocumentManager::class)
            ->call('runOcr', $doc->id);

        $this->assertSame('Haemoglobin: 14.2 g/dL', $doc->fresh()->metadata['ocr_text']);
    }
}
