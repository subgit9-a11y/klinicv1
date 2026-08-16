<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use App\Services\Auth\Permissions;

class PrescriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::PRESCRIPTIONS_VIEW);
    }

    public function view(User $user, Prescription $prescription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $prescription->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::PRESCRIPTIONS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::PRESCRIPTIONS_CREATE);
    }

    public function update(User $user, Prescription $prescription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($prescription->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::PRESCRIPTIONS_EDIT);
    }

    public function amend(User $user, Prescription $prescription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($prescription->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::PRESCRIPTIONS_EDIT);
    }

    public function cancel(User $user, Prescription $prescription): bool
    {
        return $this->amend($user, $prescription);
    }
}
