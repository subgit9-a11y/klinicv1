<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Followup;
use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Follow-ups share the consultation lifecycle permissions — scheduling a
 * follow-up is part of clinical care, so CONSULTATIONS_* gates apply.
 */
class FollowupPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::CONSULTATIONS_CREATE);
    }

    public function update(User $user, Followup $followup): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($followup->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::CONSULTATIONS_EDIT);
    }
}
