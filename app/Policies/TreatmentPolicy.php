<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreatmentBooking;
use App\Models\User;
use App\Services\Auth\Permissions;

class TreatmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::TREATMENTS_VIEW);
    }

    public function view(User $user, TreatmentBooking $booking): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $booking->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::TREATMENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::TREATMENTS_CREATE);
    }

    public function update(User $user, TreatmentBooking $booking): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($booking->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::TREATMENTS_EDIT);
    }

    public function cancel(User $user, TreatmentBooking $booking): bool
    {
        return $this->update($user, $booking);
    }
}
