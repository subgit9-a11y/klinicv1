<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Livewire\AI\SummaryPanel;
use App\Models\AiFeature;
use App\Models\AiRequest;
use App\Models\Document;
use App\Models\Investigation;
use App\Models\InvestigationResult;
use App\Models\IpdAdmission;
use App\Models\IpdDailyNote;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\AI\AiSummaryService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\AiSummarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AiSummaryTest extends TestCase
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
        $this->seed(AiSummarySeeder::class);
        $this->actingAs($this->doctor);
    }

    private function fakeGemini(string $content = 'Draft summary.'): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $content]]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20],
            ]),
        ]);
    }

    private function patient(): Patient
    {
        return Patient::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_seeder_registers_all_six_features(): void
    {
        foreach (['patient_summary', 'followup_assistant', 'lab_summary', 'document_summary', 'treatment_summary', 'ipd_summary'] as $key) {
            $this->assertTrue(AiFeature::where('key', $key)->where('is_active', true)->exists(), "Feature {$key} missing");
        }
    }

    public function test_patient_summary_returns_draft(): void
    {
        $this->fakeGemini('Patient chart summary.');
        $patient = $this->patient();

        $request = app(AiSummaryService::class)->summarizePatient($patient);

        $this->assertSame('DRAFT', $request->output_status);
        $this->assertSame('SUCCESS', $request->status);
        $this->assertSame('patient_summary', $request->feature->key);
    }

    public function test_followup_assistant_returns_draft(): void
    {
        $this->fakeGemini('Follow-up plan.');
        $patient = $this->patient();

        $request = app(AiSummaryService::class)->followupAssistant($patient);

        $this->assertSame('DRAFT', $request->output_status);
        $this->assertSame('followup_assistant', $request->feature->key);
    }

    public function test_lab_summary_includes_results_and_report_text(): void
    {
        $this->fakeGemini('Lab interpretation.');
        $patient = $this->patient();
        $inv = Investigation::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $patient->id,
            'name' => 'CBC', 'category' => 'LAB', 'status' => 'COMPLETED', 'requested_at' => now(),
        ]);
        InvestigationResult::create([
            'tenant_id' => $this->tenant->id, 'investigation_id' => $inv->id,
            'parameter' => 'Haemoglobin', 'value' => '14.2', 'unit' => 'g/dL',
            'reference_range' => '13-17', 'flag' => 'NORMAL',
        ]);
        Document::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $patient->id,
            'name' => 'report.pdf', 'type' => 'LAB_REPORT', 'disk' => 'LOCAL', 'path' => 'x.pdf',
            'mime_type' => 'application/pdf', 'size' => 10,
            'metadata' => ['investigation_id' => $inv->id, 'ocr_text' => 'OCR text here'],
        ]);

        $request = app(AiSummaryService::class)->summarizeInvestigation($inv);

        $this->assertSame('DRAFT', $request->output_status);
        $this->assertSame('lab_summary', $request->feature->key);
    }

    public function test_document_summary_returns_draft(): void
    {
        $this->fakeGemini('Document summary.');
        $doc = Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'consent.pdf', 'type' => 'CONSENT', 'disk' => 'LOCAL', 'path' => 'x.pdf',
            'mime_type' => 'application/pdf', 'size' => 10,
            'metadata' => ['ocr_text' => 'Some extracted text'],
        ]);

        $request = app(AiSummaryService::class)->summarizeDocument($doc);

        $this->assertSame('DRAFT', $request->output_status);
        $this->assertSame('document_summary', $request->feature->key);
    }

    public function test_treatment_summary_returns_draft(): void
    {
        $this->fakeGemini('Treatment progress.');
        $plan = TreatmentPlan::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $this->patient()->id,
            'name' => 'Panchakarma', 'description' => '21-day course',
            'total_sessions' => 21, 'completed_sessions' => 5, 'status' => 'ACTIVE',
        ]);

        $request = app(AiSummaryService::class)->summarizeTreatmentPlan($plan);

        $this->assertSame('DRAFT', $request->output_status);
        $this->assertSame('treatment_summary', $request->feature->key);
    }

    public function test_ipd_summary_returns_draft(): void
    {
        $this->fakeGemini('Course in hospital.');
        $admission = IpdAdmission::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $this->patient()->id,
            'ipd_number' => 'IPD-0001', 'admission_type' => 'PLANNED',
            'admission_reason' => 'Fever', 'admitted_at' => now(), 'status' => 'ADMITTED',
        ]);
        IpdDailyNote::create([
            'tenant_id' => $this->tenant->id, 'ipd_admission_id' => $admission->id,
            'user_id' => $this->doctor->id, 'note_date' => today(), 'content' => 'Stable.',
        ]);

        $request = app(AiSummaryService::class)->summarizeAdmission($admission);

        $this->assertSame('DRAFT', $request->output_status);
        $this->assertSame('ipd_summary', $request->feature->key);
    }

    public function test_unconfigured_provider_returns_error_never_approved(): void
    {
        config(['services.gemini.api_key' => '']);

        $request = app(AiSummaryService::class)->summarizePatient($this->patient());

        $this->assertSame('ERROR', $request->output_status);
        $this->assertNotSame('APPROVED', $request->output_status);
    }

    public function test_cross_tenant_context_rejected(): void
    {
        $ctx = app(TenantContext::class);
        $otherTenant = Tenant::factory()->create();
        $ctx->set($otherTenant->id);
        $foreign = Patient::factory()->create();
        $ctx->set($this->tenant->id);

        $this->expectException(ValidationException::class);

        app(AiSummaryService::class)->summarizePatient($foreign);
    }

    public function test_livewire_panel_generates_and_approves(): void
    {
        $this->fakeGemini('Panel summary.');
        $patient = $this->patient();

        Livewire::actingAs($this->doctor)
            ->test(SummaryPanel::class, [
                'contextType' => 'patient',
                'contextId' => $patient->id,
                'features' => ['patient_summary' => 'Summarize chart'],
            ])
            ->call('generate', 'patient_summary');

        $request = AiRequest::sole();
        $this->assertSame('DRAFT', $request->output_status);

        Livewire::actingAs($this->doctor)
            ->test(SummaryPanel::class, [
                'contextType' => 'patient',
                'contextId' => $patient->id,
                'features' => ['patient_summary' => 'Summarize chart'],
            ])
            ->call('approve', $request->id);

        $fresh = $request->fresh();
        $this->assertSame('APPROVED', $fresh->output_status);
        $this->assertSame($this->doctor->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_livewire_panel_forbidden_without_ai_use(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();
        $patient = $this->patient();

        Livewire::actingAs($receptionist)
            ->test(SummaryPanel::class, [
                'contextType' => 'patient',
                'contextId' => $patient->id,
                'features' => ['patient_summary' => 'Summarize chart'],
            ])
            ->assertForbidden();

        $this->assertSame(0, AiRequest::count());
    }
}
