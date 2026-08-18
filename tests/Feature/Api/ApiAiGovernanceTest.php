<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AiFeature;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\AiRequest;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAiGovernanceTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        // No Gemini key → AIManager records ERROR (proves no-auto-approve even with
        // configured key path the output is never APPROVED automatically).
        config(['services.gemini.api_key' => '']);
    }

    public function test_doctor_can_list_ai_requests(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        AiRequest::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->tokenHeader($doctor))
            ->getJson('/api/v1/ai-requests')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_receptionist_cannot_list_ai_requests(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->getJson('/api/v1/ai-requests')
            ->assertStatus(403);
    }

    public function test_generate_creates_ai_request_and_never_auto_approves(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $this->createPromptSetup('patient_summary');

        $resp = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/ai-requests', [
                'feature_key' => 'patient_summary',
                'contextable_type' => 'patient',
                'contextable_id' => $patient->id,
                'variables' => ['patient_name' => 'Raj'],
            ]);

        $resp->assertStatus(201);
        // The core invariant: output is NEVER 'APPROVED' automatically.
        $status = $resp->json('data.output_status');
        $this->assertContains($status, ['PENDING', 'DRAFT', 'ERROR']);
        $this->assertNotSame('APPROVED', $status);
    }

    public function test_generate_returns_422_for_unknown_contextable_type(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $this->createPromptSetup('patient_summary');

        $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/ai-requests', [
                'feature_key' => 'patient_summary',
                'contextable_type' => 'totally_unknown',
                'contextable_id' => 999,
            ])
            ->assertStatus(422);
    }

    public function test_doctor_can_approve_a_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $aiRequest = AiRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        $resp = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/ai-requests/{$aiRequest->id}/approve");

        $resp->assertStatus(200)
            ->assertJsonPath('data.output_status', 'APPROVED')
            ->assertJsonPath('data.is_approved', true);

        $this->assertNotNull($aiRequest->fresh()->approved_at);
        $this->assertSame($doctor->id, $aiRequest->fresh()->approved_by);
    }

    public function test_doctor_can_reject_a_draft_with_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $aiRequest = AiRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/ai-requests/{$aiRequest->id}/reject", ['reason' => 'Inaccurate content'])
            ->assertStatus(200)
            ->assertJsonPath('data.output_status', 'REJECTED')
            ->assertJsonPath('data.error', 'Inaccurate content');
    }

    public function test_receptionist_cannot_approve_a_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $aiRequest = AiRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/ai-requests/{$aiRequest->id}/approve")
            ->assertStatus(403);
    }

    public function test_cross_tenant_ai_request_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantB);
        $aiRequest = AiRequest::factory()->create(['tenant_id' => $tenantB->id]);

        // Doctor from tenantA tries to view tenantB's AI request.
        $this->setTenant($tenantA);
        $doctorA = User::factory()->forTenant($tenantA)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($doctorA))
            ->getJson("/api/v1/ai-requests/{$aiRequest->id}")
            ->assertStatus(404); // BelongsToTenant global scope hides the row.
    }

    public function test_cross_tenant_approve_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantB);
        $aiRequest = AiRequest::factory()->create([
            'tenant_id' => $tenantB->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        $this->setTenant($tenantA);
        $doctorA = User::factory()->forTenant($tenantA)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($doctorA))
            ->postJson("/api/v1/ai-requests/{$aiRequest->id}/approve")
            ->assertStatus(404);
    }

    public function test_index_filters_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        AiRequest::factory()->create(['tenant_id' => $tenant->id, 'output_status' => 'DRAFT']);
        AiRequest::factory()->create(['tenant_id' => $tenant->id, 'output_status' => 'APPROVED']);

        $resp = $this->withHeaders($this->tokenHeader($doctor))
            ->getJson('/api/v1/ai-requests?status=DRAFT')
            ->assertStatus(200);

        // All returned requests should be DRAFT.
        foreach ($resp->json('data') as $row) {
            $this->assertSame('DRAFT', $row['output_status']);
        }
    }
}
