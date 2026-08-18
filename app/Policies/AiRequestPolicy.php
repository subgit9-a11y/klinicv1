<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiRequest;
use App\Models\User;
use App\Services\Auth\Permissions;

/**
 * Authorization for the AI governance flow.
 *
 * AI clinical output is draft-only until a practitioner approves it. Viewing
 * AI requests, generating drafts, and approving/rejecting them all require
 * the `ai.use` permission (CLINIC_OWNER + DOCTOR by default). AI requests are
 * tenant-scoped: a user may only interact with requests belonging to their
 * own tenant (Super Admin bypasses).
 */
class AiRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::AI_USE);
    }

    public function view(User $user, AiRequest $aiRequest): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $aiRequest->tenant_id === $user->tenant_id
            && $user->hasPermission(Permissions::AI_USE);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission(Permissions::AI_USE);
    }

    /**
     * Approving/rejecting promotes a draft to official clinical output.
     * Only the same-tenant practitioner pool may do this (no cross-tenant
     * approval), guarded by ai.use.
     */
    public function approve(User $user, AiRequest $aiRequest): bool
    {
        return $this->view($user, $aiRequest);
    }

    public function reject(User $user, AiRequest $aiRequest): bool
    {
        return $this->view($user, $aiRequest);
    }
}
