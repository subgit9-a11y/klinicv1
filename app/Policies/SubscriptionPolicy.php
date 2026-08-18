<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Authorization for the SaaS subscription lifecycle.
 *
 * Plans are global catalogue entries — any authenticated user may list/view
 * them so tenants can choose a plan. Subscriptions are tenant-scoped: only
 * the owning tenant (via CLINIC_OWNER) or a Super Admin may view or cancel.
 */
class SubscriptionPolicy
{
    public function viewAnyPlan(User $user): bool
    {
        return true;
    }

    public function viewPlan(User $user, Plan $plan): bool
    {
        return true;
    }

    public function viewAnySubscription(User $user): bool
    {
        return true;
    }

    public function viewSubscription(User $user, Subscription $subscription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $subscription->tenant_id === $user->tenant_id;
    }

    public function activate(User $user): bool
    {
        // CLINIC_OWNER manages their tenant's subscription; Super Admin can do anything.
        return $user->isSuperAdmin() || $user->role === 'CLINIC_OWNER';
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $this->activate($user) && $this->viewSubscription($user, $subscription);
    }
}
