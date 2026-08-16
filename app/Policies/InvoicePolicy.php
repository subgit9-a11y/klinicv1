<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Auth\Permissions;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::BILLING_VIEW);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $invoice->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::BILLING_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::BILLING_CREATE);
    }

    public function refund(User $user, Invoice $invoice): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($invoice->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::BILLING_REFUND);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($invoice->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->hasPermission(Permissions::BILLING_CREATE);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $invoice->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::BILLING_CREATE);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $invoice->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::BILLING_CREATE);
    }
}
