<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

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

        return Cache::remember(
            $this->cacheKey($user),
            now()->addSeconds((int) config('klinic.cache_ttl.permissions', 300)),
            fn () => $this->resolvePermissions($user),
        );
    }

    public function can(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->forUser($user), true);
    }

    /**
     * Invalidate the cached permissions for a user — call when a role
     * changes or per-user overrides are updated.
     */
    public function flush(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * Invalidate every user holding a role (primary or extra user_roles) —
     * call after Super Admin edits the role's DB grants.
     */
    public function flushRole(string $roleName): void
    {
        User::where('role', $roleName)
            ->get(['id'])
            ->each(fn (User $u) => $this->flush($u));

        User::whereHas('rbacRoles', fn ($q) => $q->where('name', $roleName))
            ->get(['id'])
            ->each(fn (User $u) => $this->flush($u));
    }

    private function cacheKey(User $user): string
    {
        return "klinic:perms:{$user->id}";
    }

    /**
     * @return list<string>
     */
    private function resolvePermissions(User $user): array
    {
        // DB-backed RBAC: when the roles tables are synced, the DB is the
        // source of truth (Super Admin can redesign permissions without a
        // deploy); otherwise fall back to the code-defined catalog.
        $rbac = app(RbacService::class);
        $effective = $rbac->isSynced()
            ? $rbac->permissionsFor($user)
            : array_values(array_unique(RolePermissions::forRole($user->role)));

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
