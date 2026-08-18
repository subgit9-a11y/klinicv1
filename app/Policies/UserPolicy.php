<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Authorization for staff (doctor/clinical role) management. Clinic Owners
 * manage their own tenant's staff; Super Admin manages any. A user cannot
 * manage their own record through these endpoints (use Profile instead).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::STAFF_MANAGE);
    }

    public function view(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $target->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::STAFF_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::STAFF_MANAGE);
    }

    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $target->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::STAFF_MANAGE);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->id !== $target->id;
    }
}
