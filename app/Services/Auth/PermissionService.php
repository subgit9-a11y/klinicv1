<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

/**
 * Resolves whether a user holds a granular permission (Document 2 §6, §7).
 *
 * Resolution order:
 *  1. SUPER_ADMIN bypasses everything (always allowed).
 *  2. Start from the role's default permission set (RolePermissions).
 *  3. Apply per-user overrides stored on User.permissions as
 *     {grants: [...], revokes: [...]}. Grants add, revokes remove.
 *
 * No plan-based gating here; plan/feature scoping is handled by
 * FeatureService/LimitService (Phase 7). PermissionService only answers
 * RBAC permission questions so Policies/Gates can delegate to it.
 */
class PermissionService
{
    /**
     * The effective permission keys the user holds.
     *
     * @return list<string>
     */
    public function forUser(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Permissions::all();
        }

        $effective = array_values(array_unique(RolePermissions::forRole($user->role)));

        $overrides = $this->overrides($user);

        foreach ($overrides['grants'] as $permission) {
            if (! in_array($permission, $effective, true)) {
                $effective[] = $permission;
            }
        }

        foreach ($overrides['revokes'] as $permission) {
            $effective = array_values(array_diff($effective, [$permission]));
        }

        return $effective;
    }

    public function can(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->forUser($user), true);
    }

    public function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAll(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->can($user, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{grants: list<string>, revokes: list<string>}
     */
    private function overrides(User $user): array
    {
        $raw = $user->permissions;

        if (empty($raw)) {
            return ['grants' => [], 'revokes' => []];
        }

        // Stored as {grants: [...], revokes: [...]}.
        if (isset($raw['grants']) || isset($raw['revokes'])) {
            return [
                'grants' => array_values($raw['grants'] ?? []),
                'revokes' => array_values($raw['revokes'] ?? []),
            ];
        }

        // Legacy flat list: treat as grants.
        return ['grants' => array_values($raw), 'revokes' => []];
    }
}
