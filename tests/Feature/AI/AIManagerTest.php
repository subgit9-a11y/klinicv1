<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Contracts\AIProviderInterface;
use App\Models\AiFeature;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\AiRequest;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AIContextBuilder;
use App\Services\AI\AIManager;
use App\Services\AI\GeminiProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIManagerTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => '']);
    }

    public function test_gemini_provider_not_configured_without_api_key(): void
    {
        $this->assertFalse(app(GeminiProvider::class)->isConfigured());
    }

    public function test_gemini_provider_is_configured_with_api_key(): void
    {
        config(['services.gemini.api_key' => 'test_key']);

        $this->assertTrue(app(GeminiProvider::class)->isConfigured());
    }

    public function test_gemini_provider_name(): void
    {
        $this->assertSame('GEMINI', app(GeminiProvider::class)->name());
    }

    public function test_ai_interface_resolves_to_gemini(): void
    {
        $this->assertInstanceOf(GeminiProvider::class, app(AIProviderInterface::class));
    }

    public function test_complete_returns_empty_when_not_configured(): void
    {
        $result = app(GeminiProvider::class)->complete('Test prompt');

        $this->assertSame('', $result['content']);
    }

    public function test_structured_returns_empty_when_not_configured(): void
    {
        $result = app(GeminiProvider::class)->structured('Test prompt');

        $this->assertSame([], $result['data']);
    }

    public function test_ai_manager_records_error_when_not_configured(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $this->createPromptSetup('patient_summary');

        $request = app(AIManager::class)->generate('patient_summary', $patient, ['patient_name' => 'Raj']);

        $this->assertSame('ERROR', $request->status);
        $this->assertSame('ERROR', $request->output_status);
        $this->assertSame('AI provider not configured', $request->error);
    }

    public function test_ai_manager_records_error_when_no_feature(): void
    {
        config(['services.gemini.api_key' => 'test_key']);
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $request = app(AIManager::class)->generate('nonexistent_feature', $patient);

        $this->assertSame('ERROR', $request->status);
        $this->assertSame('No active prompt version found', $request->error);
    }

    public function test_ai_manager_approve_sets_approved_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $aiRequest = AiRequest::factory()->create([
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);
        // Real approver row — ai_requests.approved_by is FK'd to users and
        // MySQL enforces FKs where SQLite tolerated the phantom id 1.
        $approver = User::factory()->forTenant($tenant)->role('DOCTOR')->create();

        $approved = app(AIManager::class)->approve($aiRequest, $approver->id);

        $this->assertSame('APPROVED', $approved->output_status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame($approver->id, $approved->approved_by);
    }

    public function test_ai_manager_reject_sets_rejected_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $aiRequest = AiRequest::factory()->create([
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        $rejected = app(AIManager::class)->reject($aiRequest, 'Inaccurate content');

        $this->assertSame('REJECTED', $rejected->output_status);
        $this->assertSame('Inaccurate content', $rejected->error);
    }

    public function test_ai_request_output_is_always_draft_never_auto_approved(): void
    {
        // Even with API key configured, without a real API call the output
        // stays as ERROR/DRAFT. The key invariant: no auto-approval.
        config(['services.gemini.api_key' => 'test_key']);
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $this->createPromptSetup('patient_summary');

        // The provider will attempt a real HTTP call which fails in tests,
        // but AIManager catches the exception → ERROR status.
        $request = app(AIManager::class)->generate('patient_summary', $patient);

        // Verify output is never APPROVED automatically.
        $this->assertContains($request->output_status, ['PENDING', 'DRAFT', 'ERROR']);
        $this->assertNotSame('APPROVED', $request->output_status);
    }

    public function test_context_builder_summarizes_patient(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $summary = app(AIContextBuilder::class)->summarize($patient, ['name' => 'Raj']);

        $this->assertStringContainsString("Patient #{$patient->id}", $summary);
        $this->assertStringContainsString('Variables: name', $summary);
    }

    public function test_prompt_version_resolves_latest_active(): void
    {
        $prompt = AiPrompt::factory()->create(['key' => 'test_prompt', 'is_active' => true]);
        $v1 = AiPromptVersion::factory()->create(['ai_prompt_id' => $prompt->id, 'version' => 1]);
        $v2 = AiPromptVersion::factory()->create(['ai_prompt_id' => $prompt->id, 'version' => 2]);

        $this->assertSame(2, $prompt->activeVersion()->version);
    }

    public function test_ai_request_factory_creates_valid_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $aiRequest = AiRequest::factory()->create();

        $this->assertDatabaseHas('ai_requests', ['id' => $aiRequest->id]);
    }

    /**
     * Create a feature + prompt + prompt version for testing.
     */
    private function createPromptSetup(string $featureKey): void
    {
        $prompt = AiPrompt::factory()->create(['key' => $featureKey, 'is_active' => true]);
        AiPromptVersion::factory()->create([
            'ai_prompt_id' => $prompt->id,
            'version' => 1,
            'default_model' => 'gemini-2.0-flash',
        ]);
        AiFeature::factory()->create([
            'key' => $featureKey,
            'default_prompt_key' => $featureKey,
            'default_model' => 'gemini-2.0-flash',
            'is_active' => true,
        ]);
    }
}
