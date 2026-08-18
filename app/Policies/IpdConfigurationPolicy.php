<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Authorization for IPD infrastructure configuration (wards/rooms/beds).
 * Clinic Owners configure; other staff may view. Cross-tenant denied.
 */
class IpdConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission(Permissions::IPD_CONFIGURE)
            || $user->hasPermission(Permissions::IPD_VIEW);
    }

    public function view(User $user, IpdWard|IpdRoom|IpdBed $resource): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $resource->tenant_id === $user->tenant_id
            && ($user->hasPermission(Permissions::IPD_CONFIGURE)
                || $user->hasPermission(Permissions::IPD_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::IPD_CONFIGURE);
    }

    public function update(User $user, IpdWard|IpdRoom|IpdBed $resource): bool
    {
        return $this->create($user) && $this->view($user, $resource);
    }

    public function delete(User $user, IpdWard|IpdRoom|IpdBed $resource): bool
    {
        return $this->create($user) && $this->view($user, $resource);
    }
}
