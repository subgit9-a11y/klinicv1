<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Plan;

/**
 * Enforces plan-based usage limits (max doctors, max patients, AI requests
 * per day, etc.). Returns null for unlimited limits.
 *
 * Usage enforcement (e.g. "can I add another doctor?") should call the
 * canExceed() or remaining() methods. Actual usage counts are injected by
 * the caller.
 */
class LimitService
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    /**
     * Get the numeric limit for a plan-level column (max_users, max_doctors,
     * max_patients, max_appointments_per_day, ai_request_limit_per_day).
     * Returns null for unlimited.
     */
    public function planLimit(string $column, ?int $tenantId = null): ?int
    {
        $plan = $this->planService->effectivePlan($tenantId);

        if ($plan === null) {
            return null;
        }

        $value = $plan->{$column} ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * Get the feature-level limit from plan_features (e.g. ai_scribe → 50/day).
     * Returns null if the feature has no limit or doesn't exist.
     */
    public function featureLimit(string $featureKey, ?int $tenantId = null): ?int
    {
        $plan = $this->planService->effectivePlan($tenantId);

        if ($plan === null) {
            return null;
        }

        $feature = $plan->features()->where('feature_key', $featureKey)->first();

        if ($feature === null || $feature->limit_value === null) {
            return null;
        }

        return (int) $feature->limit_value;
    }

    /**
     * Check if adding `additional` more units would exceed the plan limit.
     * If the limit is null (unlimited), always returns false.
     *
     * @param int $currentUsage  Current count of the resource.
     * @param int $additional    Number of additional units to add (default 1).
     */
    public function canExceed(string $column, int $currentUsage, int $additional = 1, ?int $tenantId = null): bool
    {
        $limit = $this->planLimit($column, $tenantId);

        if ($limit === null) {
            return false;
        }

        return $currentUsage + $additional > $limit;
    }

    /**
     * Remaining units before the plan-level limit is reached.
     * Returns null for unlimited.
     */
    public function remaining(string $column, int $currentUsage, ?int $tenantId = null): ?int
    {
        $limit = $this->planLimit($column, $tenantId);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $currentUsage);
    }

    /**
     * Check if a feature limit would be exceeded. Uses the feature_limit
     * lookup (plan_features.limit_value).
     *
     * @param int $currentUsage  Current usage count for the period.
     * @param int $additional    Additional units requested.
     */
    public function featureWouldExceed(string $featureKey, int $currentUsage, int $additional = 1, ?int $tenantId = null): bool
    {
        $limit = $this->featureLimit($featureKey, $tenantId);

        if ($limit === null) {
            return false;
        }

        return $currentUsage + $additional > $limit;
    }
}
