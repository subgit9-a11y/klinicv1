<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Livewire\AI\AiApprovalBoard;
use App\Models\AiRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiApprovalBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenantAndUser(string $role = 'CLINIC_OWNER'): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $user = User::factory()->forTenant($tenant)->role($role)->create(['email_verified_at' => now()]);

        return [$tenant, $user];
    }

    public function test_approval_board_renders_for_doctor(): void
    {
        [$tenant, $doctor] = $this->seedTenantAndUser('DOCTOR');

        Livewire::actingAs($doctor)
            ->test(AiApprovalBoard::class)
            ->assertStatus(200)
            ->assertSee('AI Governance');
    }

    public function test_approval_board_forbidden_for_receptionist(): void
    {
        [$tenant, $receptionist] = $this->seedTenantAndUser('RECEPTIONIST');

        Livewire::actingAs($receptionist)
            ->test(AiApprovalBoard::class)
            ->assertStatus(403);
    }

    public function test_board_lists_drafts_by_default(): void
    {
        [$tenant, $doctor] = $this->seedTenantAndUser('DOCTOR');
        $draft = AiRequest::factory()->create(['tenant_id' => $tenant->id, 'output_status' => 'DRAFT']);
        $approved = AiRequest::factory()->create(['tenant_id' => $tenant->id, 'output_status' => 'APPROVED']);

        Livewire::actingAs($doctor)
            ->test(AiApprovalBoard::class)
            ->assertSee("#{$draft->id}")
            ->assertDontSee("#{$approved->id}");
    }

    public function test_doctor_can_approve_a_draft_inline(): void
    {
        [$tenant, $doctor] = $this->seedTenantAndUser('DOCTOR');
        $draft = AiRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        Livewire::actingAs($doctor)
            ->test(AiApprovalBoard::class)
            ->call('approve', $draft->id)
            ->assertHasNoErrors();

        $this->assertSame('APPROVED', $draft->fresh()->output_status);
        $this->assertNotNull($draft->fresh()->approved_at);
        $this->assertSame($doctor->id, $draft->fresh()->approved_by);
    }

    public function test_doctor_can_reject_a_draft_with_reason(): void
    {
        [$tenant, $doctor] = $this->seedTenantAndUser('DOCTOR');
        $draft = AiRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        Livewire::actingAs($doctor)
            ->test(AiApprovalBoard::class)
            ->call('confirmReject', $draft->id)
            ->set('rejectReason', 'Contains an unsafe recommendation')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame('REJECTED', $draft->fresh()->output_status);
        $this->assertSame('Contains an unsafe recommendation', $draft->fresh()->error);
    }

    public function test_status_filter_changes_visible_requests(): void
    {
        [$tenant, $doctor] = $this->seedTenantAndUser('DOCTOR');
        $approved = AiRequest::factory()->create(['tenant_id' => $tenant->id, 'output_status' => 'APPROVED']);

        Livewire::actingAs($doctor)
            ->test(AiApprovalBoard::class)
            ->set('statusFilter', 'APPROVED')
            ->assertSee("#{$approved->id}");
    }

    public function test_cross_tenant_approve_is_blocked(): void
    {
        [$tenantA, $doctorA] = $this->seedTenantAndUser('DOCTOR');
        $tenantB = Tenant::factory()->create();
        app(TenantContext::class)->set($tenantB->id);
        $draft = AiRequest::factory()->create([
            'tenant_id' => $tenantB->id,
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
        ]);

        // Doctor A's context is tenant A → the tenantB draft is invisible to the
        // BelongsToTenant scope, so findOrFail throws ModelNotFoundException and
        // the draft is never promoted.
        app(TenantContext::class)->set($tenantA->id);

        try {
            Livewire::actingAs($doctorA)
                ->test(AiApprovalBoard::class)
                ->call('approve', $draft->id);
            $this->fail('Expected cross-tenant approve to be blocked.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Expected — the draft belongs to another tenant.
        }

        $this->assertSame('DRAFT', $draft->fresh()->output_status);
    }
}
