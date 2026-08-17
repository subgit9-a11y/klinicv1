<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Investigation;
use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Investigations are ordered during the consultation workflow, so the
 * CONSULTATIONS_* permissions gate them.
 */
class InvestigationPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::CONSULTATIONS_CREATE);
    }

    public function update(User $user, Investigation $investigation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($investigation->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::CONSULTATIONS_EDIT);
    }
}
