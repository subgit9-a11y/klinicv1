<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Contracts\OCRProviderInterface;
use App\Livewire\EMR\InvestigationBoard;
use App\Models\Document;
use App\Models\Investigation;
use App\Models\InvestigationResult;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\OcrService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\Fakes\FakeOcrProvider;
use Tests\TestCase;

class InvestigationBoardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    private function investigation(array $attrs = []): Investigation
    {
        return Investigation::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'name' => 'CBC',
            'category' => 'LAB',
            'status' => 'REQUESTED',
            'requested_at' => now(),
        ], $attrs));
    }

    public function test_order_creates_requested_investigation(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->doctor)
            ->test(InvestigationBoard::class)
            ->set('patient_id', $patient->id)
            ->set('name', 'Lipid Profile')
            ->set('category', 'CARDIAC')
            ->call('order');

        $inv = Investigation::sole();
        $this->assertSame('REQUESTED', $inv->status);
        $this->assertSame('Lipid Profile', $inv->name);
        $this->assertSame('CARDIAC', $inv->category);
    }

    public function test_status_transitions(): void
    {
        $inv = $this->investigation();

        Livewire::actingAs($this->doctor)->test(InvestigationBoard::class)->call('start', $inv->id);
        $this->assertSame('IN_PROGRESS', $inv->fresh()->status);

        Livewire::actingAs($this->doctor)->test(InvestigationBoard::class)->call('complete', $inv->id);
        $fresh = $inv->fresh();
        $this->assertSame('COMPLETED', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_add_result_records_structured_row(): void
    {
        $inv = $this->investigation(['status' => 'IN_PROGRESS']);

        Livewire::actingAs($this->doctor)
            ->test(InvestigationBoard::class)
            ->call('openDetail', $inv->id)
            ->set('resultParameter', 'Haemoglobin')
            ->set('resultValue', '14.2')
            ->set('resultUnit', 'g/dL')
            ->set('resultRange', '13-17')
            ->set('resultFlag', 'NORMAL')
            ->call('addResult', $inv->id);

        $result = InvestigationResult::sole();
        $this->assertSame('Haemoglobin', $result->parameter);
        $this->assertSame('NORMAL', $result->flag);
        $this->assertSame($inv->id, $result->investigation_id);
        $this->assertSame($this->tenant->id, $result->tenant_id);
    }

    public function test_upload_report_creates_lab_report_document(): void
    {
        $inv = $this->investigation();

        Livewire::actingAs($this->doctor)
            ->test(InvestigationBoard::class)
            ->call('openDetail', $inv->id)
            ->set('reportFile', UploadedFile::fake()->create('lab-report.pdf', 256, 'application/pdf'))
            ->call('uploadReport', $inv->id);

        $doc = Document::sole();
        $this->assertSame('LAB_REPORT', $doc->type);
        $this->assertSame($inv->patient_id, $doc->patient_id);
        $this->assertSame($inv->id, $doc->metadata['investigation_id']);

        app(\App\Services\Storage\S3StorageProvider::class)->delete($doc->path);
    }

    public function test_run_ocr_stamps_text_on_document(): void
    {
        app()->forgetInstance(OcrService::class);
        app()->forgetInstance(OCRProviderInterface::class);
        app()->instance(OCRProviderInterface::class, new FakeOcrProvider());

        $inv = $this->investigation();
        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $inv->patient_id,
            'name' => 'report.pdf',
            'type' => 'LAB_REPORT',
            'disk' => 'local',
            'path' => 'pdfs/test/report.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'metadata' => ['investigation_id' => $inv->id],
        ]);

        Livewire::actingAs($this->doctor)
            ->test(InvestigationBoard::class)
            ->call('openDetail', $inv->id)
            ->call('runOcr', $doc->id);

        $metadata = $doc->fresh()->metadata;
        $this->assertSame('Haemoglobin: 14.2 g/dL', $metadata['ocr_text']);
        $this->assertSame('FAKE_OCR', $metadata['ocr_provider']);
    }

    public function test_nurse_can_view_but_not_order_or_transition(): void
    {
        $nurse = User::factory()->forTenant($this->tenant)->role('NURSE')->create();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $inv = $this->investigation();

        Livewire::actingAs($nurse)->test(InvestigationBoard::class)->assertSee('Investigations');

        Livewire::actingAs($nurse)
            ->test(InvestigationBoard::class)
            ->set('patient_id', $patient->id)
            ->set('name', 'X-Ray')
            ->call('order')
            ->assertForbidden();

        Livewire::actingAs($nurse)
            ->test(InvestigationBoard::class)
            ->call('complete', $inv->id)
            ->assertForbidden();

        $this->assertSame('REQUESTED', $inv->fresh()->status);
    }

    public function test_cross_tenant_investigation_not_accessible(): void
    {
        $otherTenant = Tenant::factory()->create();
        $ctx = app(TenantContext::class);
        $ctx->set($otherTenant->id);
        $foreign = Investigation::create([
            'tenant_id' => $otherTenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'name' => 'MRI',
            'category' => 'RADIOLOGY',
            'status' => 'REQUESTED',
            'requested_at' => now(),
        ]);
        $ctx->set($this->tenant->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->doctor)
            ->test(InvestigationBoard::class)
            ->call('complete', $foreign->id);
    }
}
