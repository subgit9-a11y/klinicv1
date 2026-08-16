<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Plan;
use App\Models\PlanFeature;

/**
 * Checks whether a feature is enabled for the tenant's current plan.
 *
 * All feature gating is database-driven via the plan_features table. No
 * hard-coded plan checks — add a row to plan_features and the feature is
 * automatically gated.
 */
class FeatureService
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    /**
     * Check if a feature (by key) is enabled for the current tenant's plan.
     */
    public function enabled(string $featureKey, ?int $tenantId = null): bool
    {
        $plan = $this->planService->effectivePlan($tenantId);

        if ($plan === null) {
            return false;
        }

        // Check plan-level boolean flags first (ipd_enabled, treatments_enabled).
        if ($this->checkPlanLevelFlag($plan, $featureKey)) {
            return true;
        }

        $feature = $this->getFeature($plan, $featureKey);

        return $feature !== null && $feature->enabled;
    }

    /**
     * Get the PlanFeature record for a feature key, or null if not defined.
     */
    public function getFeature(Plan $plan, string $featureKey): ?PlanFeature
    {
        return $plan->features()
            ->where('feature_key', $featureKey)
            ->first();
    }

    /**
     * Map feature keys to the plan-level boolean columns for backwards
     * compatibility with plans that predate the plan_features table.
     */
    private function checkPlanLevelFlag(Plan $plan, string $featureKey): bool
    {
        return match ($featureKey) {
            'ipd' => $plan->ipd_enabled,
            'treatments' => $plan->treatments_enabled,
            default => false,
        };
    }
}
