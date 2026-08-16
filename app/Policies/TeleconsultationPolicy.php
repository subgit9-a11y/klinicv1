<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Teleconsultation;
use App\Models\User;
use App\Services\Auth\Permissions;

class TeleconsultationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::APPOINTMENTS_VIEW);
    }

    public function view(User $user, Teleconsultation $teleconsultation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $teleconsultation->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::APPOINTMENTS_VIEW);
    }
}
