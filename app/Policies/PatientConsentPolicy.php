<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PatientConsent;
use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Consents are captured during clinical care and gated by the
 * CONSULTATIONS_* permissions.
 */
class PatientConsentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::CONSULTATIONS_CREATE);
    }

    public function update(User $user, PatientConsent $consent): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($consent->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::CONSULTATIONS_EDIT);
    }
}
