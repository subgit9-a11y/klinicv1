<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ((int) $appointment->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        // Doctors may always view their own appointments.
        if ($appointment->user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('appointments.view');
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('appointments.view');
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('appointments.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ((int) $appointment->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        if ($appointment->user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('appointments.edit');
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ((int) $appointment->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        if ($appointment->user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('appointments.cancel');
    }

    public function manageQueue(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('queue.manage');
    }
}
