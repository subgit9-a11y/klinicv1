<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Services\Auth\Permissions;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission(Permissions::BILLING_VIEW)
            || $user->hasPermission(Permissions::CASH_REGISTER_MANAGE);
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $expense->tenant_id === $user->tenant_id
            && ($user->hasPermission(Permissions::BILLING_VIEW)
                || $user->hasPermission(Permissions::CASH_REGISTER_MANAGE));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::CASH_REGISTER_MANAGE);
    }
}
