<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Plans\PlanService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSubscriptionTest extends TestCase
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

    public function test_any_user_can_list_plans(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        Plan::factory()->create(['code' => 'SOLO_DOCTOR', 'is_active' => true]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->getJson('/api/v1/plans')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    }

    public function test_clinic_owner_can_activate_subscription(): void
    {
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $plan = Plan::factory()->create(['code' => 'SMALL_CLINIC', 'is_active' => true, 'billing_cycle' => 'MONTHLY']);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/subscription/activate', ['plan_code' => 'SMALL_CLINIC'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.plan_id', $plan->id);

        $this->assertSame('SMALL_CLINIC', $tenant->fresh()->plan_code);
    }

    public function test_receptionist_cannot_activate_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        Plan::factory()->create(['code' => 'SMALL_CLINIC', 'is_active' => true]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson('/api/v1/subscription/activate', ['plan_code' => 'SMALL_CLINIC'])
            ->assertStatus(403);
    }

    public function test_current_subscription_returns_active(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $plan = Plan::factory()->create(['code' => 'SOLO_DOCTOR', 'is_active' => true]);
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
        ]);

        $this->withHeaders($this->tokenHeader($owner))
            ->getJson('/api/v1/subscription')
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_clinic_owner_can_cancel_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $plan = Plan::factory()->create(['code' => 'SOLO_DOCTOR']);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
        ]);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson("/api/v1/subscriptions/{$subscription->id}/cancel", ['reason' => 'Closing clinic'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'CANCELLED')
            ->assertJsonPath('data.cancel_reason', 'Closing clinic');
    }

    public function test_cannot_view_other_tenant_subscription(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $ownerA = User::factory()->forTenant($tenantA)->create(['role' => 'CLINIC_OWNER']);

        $ctx = app(TenantContext::class);
        $ctx->set($tenantB->id);
        $plan = Plan::factory()->create();
        $subscriptionB = Subscription::factory()->create(['tenant_id' => $tenantB->id, 'plan_id' => $plan->id]);
        $ctx->set($tenantA->id);

        $this->withHeaders($this->tokenHeader($ownerA))
            ->postJson("/api/v1/subscriptions/{$subscriptionB->id}/cancel")
            ->assertNotFound();
    }

    public function test_activate_with_unknown_plan_code_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/subscription/activate', ['plan_code' => 'NON_EXISTENT'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan_code']);
    }

    public function test_service_activate_records_event(): void
    {
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        Plan::factory()->create(['code' => 'SMALL_CLINIC', 'is_active' => true, 'billing_cycle' => 'YEARLY']);

        $subscription = app(PlanService::class)->activate($tenant->id, 'SMALL_CLINIC');

        $this->assertSame('ACTIVE', $subscription->status);
        $this->assertCount(1, $subscription->events);
        $this->assertSame('ACTIVATED', $subscription->events->first()->event_type);
    }
}
