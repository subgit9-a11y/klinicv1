<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Auth\Permissions;

class NotificationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::NOTIFICATIONS_MANAGE);
    }

    public function view(User $user, NotificationTemplate $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        // Tenant users can view their own + global templates.
        if ($template->tenant_id === null) {
            return $user->hasPermission(Permissions::NOTIFICATIONS_MANAGE);
        }

        return $template->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::NOTIFICATIONS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::NOTIFICATIONS_MANAGE);
    }

    public function update(User $user, NotificationTemplate $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        // Tenant users cannot modify global templates.
        if ($template->tenant_id === null) {
            return false;
        }

        return $template->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::NOTIFICATIONS_MANAGE);
    }

    public function delete(User $user, NotificationTemplate $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($template->tenant_id === null) {
            return false;
        }

        return $template->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::NOTIFICATIONS_MANAGE);
    }
}
