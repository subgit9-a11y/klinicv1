<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Role;
use App\Models\User;

/**
 * Database-backed RBAC resolution. The DB (roles/permissions/
 * role_permissions/user_roles) is the source of truth when it has data;
 * the code-defined RolePermissions catalog is the fallback pre-sync and
 * the seed content for the sync.
 */
class RbacService
{
    /**
     * Effective permission keys for a user: their primary role (users.role)
     * plus any extra roles (user_roles), resolved from the DB when synced,
     * else from the code catalog.
     *
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        $roleNames = array_values(array_unique(array_merge(
            [$user->role],
            $user->rbacRoles()->pluck('name')->all(),
        )));

        return $this->permissionsForRoles($roleNames);
    }

    /**
     * Permission keys for a set of role names. DB-backed when any of those
     * roles has role_permissions rows; else falls back to the code
     * catalog (primary role only).
     *
     * @var list<string>
     * @return list<string>
     */
    public function permissionsForRoles(array $roleNames): array
    {
        $roles = Role::with('permissions:id,key')->whereIn('name', $roleNames)->get();

        if ($roles->isNotEmpty() && $roles->some(fn (Role $r) => $r->permissions->isNotEmpty())) {
            return $roles->flatMap(fn (Role $r) => $r->permissions->pluck('key'))->unique()->values()->all();
        }

        // Pre-sync fallback: the code-defined catalog still answers.
        return array_values(array_unique(RolePermissions::forRole($roleNames[0] ?? '')));
    }

    /**
     * Idempotently mirror the code-defined catalog into the DB tables.
     * Existing DB rows are preserved; grants from the catalog are upserted.
     */
    public function syncFromCode(): array
    {
        $counts = ['roles' => 0, 'permissions' => 0, 'grants' => 0];

        foreach (RolePermissions::roles() as $roleName) {
            $permissions = RolePermissions::forRole($roleName);
            /** @var Role $role */
            $role = Role::firstOrCreate(['name' => $roleName]);
            $counts['roles']++;

            foreach ($permissions as $key) {
                $permission = Permission::firstOrCreate(['key' => $key]);
                $counts['permissions']++;
                $role->permissions()->syncWithoutDetaching([$permission->id]);
                $counts['grants']++;
            }
        }

        return $counts;
    }

    /**
     * Whether the DB has any role data (i.e. sync was run at least once).
     */
    public function isSynced(): bool
    {
        return Role::exists();
    }

    public function rolesWithCounts(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::withCount('permissions')->orderBy('name')->get();
    }

    public function permissionsGrouped(): \Illuminate\Database\Eloquent\Collection
    {
        return Permission::orderBy('key')->get()->groupBy(fn (Permission $p) => explode('.', $p->key)[0]);
    }

    public function createRole(string $name, ?string $description = null): Role
    {
        return Role::create(['name' => $name, 'description' => $description]);
    }

    public function updateRole(Role $role, string $name, ?string $description = null): Role
    {
        $role->update(['name' => $name, 'description' => $description]);

        return $role->refresh();
    }

    /**
     * Role deletion is refused when users hold it (primary or extra), and
     * SUPER_ADMIN is never deletable as a safety rail.
     */
    public function deleteRole(Role $role): void
    {
        if ($role->name === 'SUPER_ADMIN') {
            throw new \DomainException('The SUPER_ADMIN role cannot be deleted.');
        }

        if (User::where('role', $role->name)->exists() || $role->users()->exists()) {
            throw new \DomainException("Role {$role->name} is assigned to users and cannot be deleted.");
        }

        $role->delete();
    }

    public function deletePermission(Permission $permission): void
    {
        $permission->delete();
    }
}
