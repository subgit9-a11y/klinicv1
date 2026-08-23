<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RbacService;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Super Admin DB-backed RBAC editor: roles, permission grants per role,
 * custom permission keys, extra roles per user, and one-click sync from
 * the code-defined catalog.
 */
class RbacManagement extends Component
{
    public string $activeTab = 'roles';

    // Role form
    public ?int $editingRoleId = null;

    public string $role_name = '';

    public ?string $role_description = null;

    // Permission editor
    public ?int $selectedRoleId = null;

    // New permission form
    public string $permission_key = '';

    public ?string $permission_description = null;

    // User roles form
    public string $userSearch = '';

    public ?int $selectedUserId = null;

    public function mount(): void
    {
        $this->guard();
    }

    // --- Roles ---

    public function saveRole(RbacService $rbac): void
    {
        $this->guard();

        $this->validate([
            'role_name' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('roles', 'name')->ignore($this->editingRoleId)],
            'role_description' => 'nullable|string|max:255',
        ]);

        if ($this->editingRoleId !== null) {
            $rbac->updateRole(Role::findOrFail($this->editingRoleId), $this->role_name, $this->role_description);
        } else {
            $rbac->createRole($this->role_name, $this->role_description);
        }

        session()->flash('message', 'Role saved.');
        $this->resetRoleForm();
    }

    public function editRole(int $roleId): void
    {
        $this->guard();

        $role = Role::findOrFail($roleId);
        $this->editingRoleId = $role->id;
        $this->role_name = $role->name;
        $this->role_description = $role->description;
    }

    public function deleteRole(int $roleId, RbacService $rbac): void
    {
        $this->guard();

        try {
            $rbac->deleteRole(Role::findOrFail($roleId));
            session()->flash('message', 'Role deleted.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->resetRoleForm();
    }

    public function resetRoleForm(): void
    {
        $this->reset(['editingRoleId', 'role_name', 'role_description']);
    }

    // --- Permission grants ---

    public function selectRole(int $roleId): void
    {
        $this->guard();

        $this->selectedRoleId = $roleId;
    }

    public function togglePermission(int $roleId, int $permissionId, PermissionService $perms): void
    {
        $this->guard();

        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        if ($role->permissions()->where('permission_id', $permissionId)->exists()) {
            $role->permissions()->detach($permissionId);
        } else {
            $role->permissions()->attach($permissionId);
        }

        $perms->flushRole($role->name);
    }

    public function createPermission(): void
    {
        $this->guard();

        $this->validate([
            'permission_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_.]+$/', Rule::unique('permissions', 'key')],
            'permission_description' => 'nullable|string|max:255',
        ]);

        Permission::create(['key' => $this->permission_key, 'description' => $this->permission_description]);

        session()->flash('message', "Permission {$this->permission_key} created.");
        $this->reset(['permission_key', 'permission_description']);
    }

    public function deletePermission(int $permissionId, RbacService $rbac): void
    {
        $this->guard();

        $rbac->deletePermission(Permission::findOrFail($permissionId));
        session()->flash('message', 'Permission deleted.');
    }

    // --- User extra roles ---

    public function selectUser(int $userId): void
    {
        $this->guard();

        $this->selectedUserId = $userId;
    }

    public function toggleUserRole(int $userId, int $roleId, PermissionService $perms): void
    {
        $this->guard();

        $user = User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        if ($user->rbacRoles()->where('role_id', $roleId)->exists()) {
            $user->rbacRoles()->detach($roleId);
        } else {
            $user->rbacRoles()->attach($roleId);
        }

        $perms->flush($user);
    }

    /**
     * Per-user override toggle: $kind is 'grants' (explicit allow) or
     * 'revokes' (explicit deny) on the user.permissions JSON catalog.
     */
    public function toggleUserPermission(int $userId, string $permissionKey, string $kind, PermissionService $perms): void
    {
        $this->guard();
        abort_unless(in_array($kind, ['grants', 'revokes'], true), 422);

        $user = User::findOrFail($userId);
        $overrides = $user->permissions ?? [];

        $list = collect($overrides[$kind] ?? []);
        $overrides[$kind] = $list->contains($permissionKey)
            ? $list->reject(fn ($key) => $key === $permissionKey)->values()->all()
            : $list->push($permissionKey)->values()->all();

        $user->permissions = $overrides;
        $user->save();

        $perms->flush($user);
        session()->flash('message', "User override saved ({$kind}: {$permissionKey}).");
    }

    // --- Sync ---

    public function syncFromCode(RbacService $rbac, PermissionService $perms): void
    {
        $this->guard();

        $counts = $rbac->syncFromCode();

        // Everything may have changed; flush all cached permission sets.
        User::query()->get(['id'])->each(fn (User $u) => $perms->flush($u));

        session()->flash('message', "Synced {$counts['roles']} roles, {$counts['permissions']} permissions, {$counts['grants']} grants from code defaults.");
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
    }

    public function render()
    {
        $this->guard();

        $rbac = app(RbacService::class);

        $selectedRole = $this->selectedRoleId !== null ? Role::with('permissions:id,key')->find($this->selectedRoleId) : null;
        $selectedUser = $this->selectedUserId !== null ? User::with('rbacRoles:id,name')->find($this->selectedUserId) : null;

        $userResults = $this->userSearch !== ''
            ? User::where(fn ($q) => $q->where('name', 'like', "%{$this->userSearch}%")->orWhere('email', 'like', "%{$this->userSearch}%"))->limit(8)->get(['id', 'name', 'email', 'role'])
            : collect();

        return view('livewire.super-admin.rbac-management', [
            'roles' => $rbac->rolesWithCounts(),
            'permissionsGrouped' => $rbac->permissionsGrouped(),
            'selectedRole' => $selectedRole,
            'grantedKeys' => $selectedRole?->permissions->pluck('key')->all() ?? [],
            'allRoles' => Role::orderBy('name')->get(['id', 'name']),
            'userResults' => $userResults,
            'selectedUser' => $selectedUser,
            'isSynced' => $rbac->isSynced(),
        ])->layout('components.layouts.app');
    }
}
