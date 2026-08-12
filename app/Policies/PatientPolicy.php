<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Services\Auth\Permissions;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        // Tenant users can only view patients in their own tenant; Super Admin
        // (null tenant) can view any patient.
        return $user->isSuperAdmin() || $user->tenant_id === $patient->tenant_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permissions::PATIENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::PATIENTS_CREATE);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermission(Permissions::PATIENTS_EDIT)
            && ($user->isSuperAdmin() || $user->tenant_id === $patient->tenant_id);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasPermission(Permissions::PATIENTS_DELETE)
            && ($user->isSuperAdmin() || $user->tenant_id === $patient->tenant_id);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission(Permissions::PATIENTS_EXPORT);
    }
}
