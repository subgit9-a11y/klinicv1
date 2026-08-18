<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Authorization for treatment catalogue configuration. Clinic Owners manage
 * the catalogue; other staff may view. Cross-tenant access denied.
 */
class TreatmentCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission(Permissions::TREATMENTS_MANAGE)
            || $user->hasPermission(Permissions::TREATMENTS_VIEW);
    }

    public function view(User $user, TreatmentService|TreatmentRoom $resource): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $resource->tenant_id === $user->tenant_id
            && ($user->hasPermission(Permissions::TREATMENTS_MANAGE)
                || $user->hasPermission(Permissions::TREATMENTS_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::TREATMENTS_MANAGE);
    }

    public function update(User $user, TreatmentService|TreatmentRoom $resource): bool
    {
        return $this->create($user) && $this->view($user, $resource);
    }

    public function delete(User $user, TreatmentService|TreatmentRoom $resource): bool
    {
        return $this->create($user) && $this->view($user, $resource);
    }
}
