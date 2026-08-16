<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central read/write service for plans and subscriptions.
 *
 * Plan configuration is database-driven (PlanSeeder) — no hard-coded plan
 * checks exist anywhere in the application. All feature/limit gating flows
 * through FeatureService and LimitService, which read from this service.
 */
class PlanService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * All active plans, ordered by price ascending.
     *
     * @return Collection<int, Plan>
     */
    public function activePlans(): Collection
    {
        return Plan::where('is_active', true)->orderBy('price_cents')->get();
    }

    public function findByCode(string $code): ?Plan
    {
        return Plan::where('code', $code)->first();
    }

    /**
     * Resolve the active subscription for the current (or given) tenant.
     * Falls back to the tenant's plan_code column if no subscription record
     * exists yet (e.g. during onboarding / trial).
     */
    public function activeSubscription(?int $tenantId = null): ?Subscription
    {
        $tenantId ??= $this->tenantContext->id();
        if ($tenantId === null) {
            return null;
        }

        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderByDesc('id')
            ->first();

        if ($subscription !== null) {
            return $subscription;
        }

        return $this->createTrialSubscription($tenantId);
    }

    /**
     * The Plan currently in effect for the tenant (via subscription or
     * tenant.plan_code fallback).
     */
    public function effectivePlan(?int $tenantId = null): ?Plan
    {
        $subscription = $this->activeSubscription($tenantId);

        if ($subscription !== null && $subscription->plan_id !== null) {
            return $subscription->plan;
        }

        $tenantId ??= $this->tenantContext->id();
        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant !== null && $tenant->plan_code !== null) {
            return $this->findByCode($tenant->plan_code);
        }

        return null;
    }

    /**
     * Create a subscription record for a tenant (used during onboarding or
     * when activating a plan). Runs inside a transaction and records the
     * ACTIVATED event.
     */
    public function activate(int $tenantId, string $planCode, ?string $gatewaySubscriptionId = null): Subscription
    {
        $plan = $this->findByCode($planCode);
        if ($plan === null) {
            throw new \InvalidArgumentException("Unknown plan code: {$planCode}");
        }

        return DB::transaction(function () use ($tenantId, $plan, $gatewaySubscriptionId) {
            Subscription::where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'CANCELLED',
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Replaced by new subscription',
                ]);

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'status' => 'ACTIVE',
                'gateway_subscription_id' => $gatewaySubscriptionId,
                'starts_at' => now(),
                'ends_at' => $plan->billing_cycle === 'YEARLY'
                    ? now()->addYear()
                    : now()->addMonth(),
            ]);

            $subscription->events()->create([
                'event_type' => 'ACTIVATED',
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'gateway_event_id' => $gatewaySubscriptionId,
            ]);

            Tenant::where('id', $tenantId)->update(['plan_code' => $plan->code]);

            return $subscription->refresh();
        });
    }

    public function cancel(Subscription $subscription, string $reason = 'User requested'): void
    {
        DB::transaction(function () use ($subscription, $reason) {
            $subscription->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $subscription->events()->create([
                'event_type' => 'CANCELLED',
                'reason' => $reason,
            ]);
        });
    }

    /**
     * If a tenant has no subscription, create an implicit trial subscription
     * based on the tenant's plan_code (or default to SOLO_DOCTOR).
     */
    private function createTrialSubscription(int $tenantId): ?Subscription
    {
        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return null;
        }

        $planCode = $tenant->plan_code ?? 'SOLO_DOCTOR';
        $plan = $this->findByCode($planCode);
        if ($plan === null) {
            return null;
        }

        return Subscription::create([
            'tenant_id' => $tenantId,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'starts_at' => now(),
            'ends_at' => $tenant->trial_ends_at ?? now()->addDays(14),
        ]);
    }
}
