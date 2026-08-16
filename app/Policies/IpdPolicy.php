<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IpdAdmission;
use App\Models\User;
use App\Services\Auth\Permissions;

class IpdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::IPD_VIEW);
    }

    public function view(User $user, IpdAdmission $admission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $admission->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::IPD_VIEW);
    }

    public function admit(User $user): bool
    {
        return $user->hasPermission(Permissions::IPD_ADMIT);
    }

    public function discharge(User $user, IpdAdmission $admission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($admission->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::IPD_DISCHARGE);
    }

    public function createNote(User $user, IpdAdmission $admission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($admission->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::IPD_NOTES);
    }
}
