<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plans\FeatureService;
use App\Services\Plans\LimitService;
use App\Services\Plans\PlanService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_active_plans_returns_sorted_by_price(): void
    {
        $this->seedPlans();

        $plans = app(PlanService::class)->activePlans();

        $this->assertCount(2, $plans);
        $this->assertSame('SOLO_DOCTOR', $plans->first()->code);
        $this->assertSame('SMALL_CLINIC', $plans->last()->code);
    }

    public function test_find_by_code_returns_plan(): void
    {
        $this->seedPlans();

        $plan = app(PlanService::class)->findByCode('SOLO_DOCTOR');

        $this->assertNotNull($plan);
        $this->assertSame(99900, $plan->price_cents);
    }

    public function test_effective_plan_falls_back_to_tenant_plan_code(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $plan = app(PlanService::class)->effectivePlan();

        $this->assertNotNull($plan);
        $this->assertSame('SOLO_DOCTOR', $plan->code);
    }

    public function test_effective_plan_uses_active_subscription(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $smallClinic = app(PlanService::class)->findByCode('SMALL_CLINIC');

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $smallClinic->id,
            'status' => 'ACTIVE',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $plan = app(PlanService::class)->effectivePlan();

        $this->assertSame('SMALL_CLINIC', $plan->code);
    }

    public function test_activate_creates_subscription_and_cancels_previous(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $solo = app(PlanService::class)->findByCode('SOLO_DOCTOR');

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $solo->id,
            'status' => 'ACTIVE',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $subscription = app(PlanService::class)->activate($tenant->id, 'SMALL_CLINIC');

        $this->assertSame('ACTIVE', $subscription->status);
        $this->assertSame('SMALL_CLINIC', $subscription->plan->code);
        $this->assertSame('SMALL_CLINIC', $tenant->fresh()->plan_code);

        $activeCount = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'ACTIVE')
            ->count();
        $this->assertSame(1, $activeCount);

        $cancelled = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'CANCELLED')
            ->count();
        $this->assertSame(1, $cancelled);

        $this->assertGreaterThan(0, $subscription->events()->count());
    }

    public function test_cancel_sets_status_and_reason(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $plan = app(PlanService::class)->findByCode('SOLO_DOCTOR');
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        app(PlanService::class)->cancel($subscription, 'No longer needed');

        $subscription->refresh();
        $this->assertSame('CANCELLED', $subscription->status);
        $this->assertSame('No longer needed', $subscription->cancel_reason);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_feature_service_checks_plan_features_table(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $featureService = app(FeatureService::class);

        $this->assertTrue($featureService->enabled('patients'));
        $this->assertTrue($featureService->enabled('emr'));
        $this->assertFalse($featureService->enabled('ipd'));
    }

    public function test_feature_service_checks_plan_level_flags(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SMALL_CLINIC']);
        $this->setTenant($tenant);

        $featureService = app(FeatureService::class);

        $this->assertTrue($featureService->enabled('ipd'));
        $this->assertTrue($featureService->enabled('treatments'));
    }

    public function test_feature_service_returns_false_when_no_plan(): void
    {
        $featureService = app(FeatureService::class);

        $this->assertFalse($featureService->enabled('patients'));
    }

    public function test_limit_service_returns_plan_level_limits(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $limitService = app(LimitService::class);

        $this->assertSame(1, $limitService->planLimit('max_doctors'));
        $this->assertSame(1, $limitService->planLimit('max_users'));
    }

    public function test_limit_service_returns_null_for_unlimited(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $limitService = app(LimitService::class);

        $this->assertNull($limitService->planLimit('max_patients'));
    }

    public function test_limit_service_returns_feature_limits(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $limitService = app(LimitService::class);

        $this->assertSame(50, $limitService->featureLimit('ai_scribe'));
        $this->assertSame(50, $limitService->featureLimit('ai_patient_summary'));
    }

    public function test_can_exceed_detects_limit_breach(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $limitService = app(LimitService::class);

        $this->assertFalse($limitService->canExceed('max_doctors', 0, 1));
        $this->assertTrue($limitService->canExceed('max_doctors', 1, 1));
    }

    public function test_remaining_calculates_correctly(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $limitService = app(LimitService::class);

        $this->assertSame(1, $limitService->remaining('max_doctors', 0));
        $this->assertSame(0, $limitService->remaining('max_doctors', 1));
        $this->assertSame(0, $limitService->remaining('max_doctors', 5));
    }

    public function test_feature_would_exceed_checks_feature_limit(): void
    {
        $this->seedPlans();
        $tenant = Tenant::factory()->create(['plan_code' => 'SOLO_DOCTOR']);
        $this->setTenant($tenant);

        $limitService = app(LimitService::class);

        $this->assertFalse($limitService->featureWouldExceed('ai_scribe', 49, 1));
        $this->assertTrue($limitService->featureWouldExceed('ai_scribe', 50, 1));
    }

    private function seedPlans(): void
    {
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }
}
