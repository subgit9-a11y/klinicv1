<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;
use App\Services\Auth\Permissions;

class ConsultationPolicy
{
    public function view(User $user, Consultation $consultation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $consultation->tenant_id === $user->tenant_id && $user->hasPermission(Permissions::CONSULTATIONS_VIEW);
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::CONSULTATIONS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::CONSULTATIONS_CREATE);
    }

    public function update(User $user, Consultation $consultation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($consultation->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::CONSULTATIONS_EDIT);
    }

    public function complete(User $user, Consultation $consultation): bool
    {
        return $this->update($user, $consultation);
    }

    public function amend(User $user, Consultation $consultation): bool
    {
        return $this->update($user, $consultation);
    }

    public function recordVitals(User $user): bool
    {
        return $user->hasPermission(Permissions::VITALS_MANAGE);
    }

    public function manageDiagnoses(User $user, Consultation $consultation): bool
    {
        return $this->update($user, $consultation);
    }
}
