<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;
use App\Services\Auth\Permissions;

class CashRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::CASH_REGISTER_MANAGE);
    }

    public function view(User $user, CashRegister $register): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $register->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::CASH_REGISTER_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::CASH_REGISTER_MANAGE);
    }

    public function update(User $user, CashRegister $register): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $register->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::CASH_REGISTER_MANAGE);
    }
}
