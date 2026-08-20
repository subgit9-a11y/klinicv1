<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\AiRequest;
use App\Models\Consultation;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiScribeService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiScribeTest extends TestCase
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

    private function consultation(): Consultation
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        return Consultation::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $this->doctor->id,
            'chief_complaint' => 'Joint pain for three days',
            'status' => 'DRAFT',
        ]);
    }

    public function test_parse_output_extracts_soap_sections(): void
    {
        $service = app(AiScribeService::class);

        $soap = $service->parseOutput('{"subjective":"low back pain","objective":"tender L4-L5","assessment":"lumbago","plan":"rest + physio"}');

        $this->assertSame('low back pain', $soap['subjective']);
        $this->assertSame('tender L4-L5', $soap['objective']);
        $this->assertSame('lumbago', $soap['assessment']);
        $this->assertSame('rest + physio', $soap['plan']);
    }

    public function test_parse_output_handles_provider_envelope(): void
    {
        $service = app(AiScribeService::class);

        $soap = $service->parseOutput('{"content":"{\"subjective\": \"cough\", \"objective\": \"mild fever\"}"}');

        $this->assertSame('cough', $soap['subjective']);
        $this->assertSame('mild fever', $soap['objective']);
    }

    public function test_draft_from_complaint_creates_draft_request_not_final(): void
    {
        config(['services.gemini.api_key' => 'not-configured']);

        $consultation = $this->consultation();

        $request = app(AiScribeService::class)->draftFromChiefComplaint($consultation);

        // Unconfigured provider → ERROR (still NOT APPROVED, and no data
        // leaked into the final consultation).
        $this->assertSame('ERROR', $request->output_status);
        $this->assertNotSame('APPROVED', $request->output_status);
        $this->assertNull($consultation->fresh()->history);
    }

    public function test_approve_writes_soap_fields_to_consultation(): void
    {
        $consultation = $this->consultation();

        $request = AiRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'contextable_type' => Consultation::class,
            'contextable_id' => $consultation->id,
            'output_status' => 'DRAFT',
            'output' => '{"subjective":"pain history","objective":"tenderness","assessment":"sciatica","plan":"PT referral"}',
        ]);

        $approved = app(AiScribeService::class)->approve($request, $this->doctor);

        $this->assertSame('pain history', $approved->history);
        $this->assertSame('tenderness', $approved->examination);
        $this->assertSame('sciatica', $approved->assessment);
        $this->assertSame('PT referral', $approved->treatment_plan);

        $this->assertSame('APPROVED', $request->fresh()->output_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.scribe.approved']);
    }

    public function test_approve_uses_edited_output_when_supplied(): void
    {
        $consultation = $this->consultation();

        $request = AiRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'contextable_type' => Consultation::class,
            'contextable_id' => $consultation->id,
            'output_status' => 'DRAFT',
            'output' => '{"subjective":"original"}',
        ]);

        $approved = app(AiScribeService::class)->approve($request, $this->doctor, [
            'subjective' => 'edited by doctor',
        ]);

        $this->assertSame('edited by doctor', $approved->history);
    }
}
