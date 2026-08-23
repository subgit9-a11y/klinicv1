<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Roles & Permissions</h1>
            <p class="text-sm text-gray-600">Database-backed RBAC — changes take effect for every user without a deploy.</p>
        </div>
        @if (! $isSynced)
            <div class="text-sm">
                <span class="inline-block mr-3 px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Not synced — code defaults active</span>
            </div>
        @endif
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex items-center justify-between">
        <div class="flex gap-2 border-b border-gray-200">
            @foreach (['roles' => 'Roles', 'permissions' => 'Permissions', 'users' => 'User roles'] as $tab => $label)
                <button wire:click="setTab('{{ $tab }}')" class="px-4 py-2 text-sm font-medium {{ $activeTab === $tab ? 'text-brand-700 border-b-2 border-brand-600' : 'text-gray-500' }}">{{ $label }}</button>
            @endforeach
        </div>
        <button wire:click="syncFromCode" wire:confirm="Mirror the code-defined role defaults into the database? Existing DB roles are kept." class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm font-medium">
            Sync from code defaults
        </button>
    </div>

    @if ($activeTab === 'roles')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($roles as $role)
                            <tr class="{{ $selectedRoleId === $role->id ? 'bg-brand-50' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $role->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $role->description }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $role->permissions_count }}</td>
                                <td class="px-4 py-3 text-right space-x-1 text-sm">
                                    <button wire:click="selectRole({{ $role->id }})" class="text-brand-600 hover:underline">Grants</button>
                                    <button wire:click="editRole({{ $role->id }})" class="text-brand-600 hover:underline">Edit</button>
                                    <button wire:click="deleteRole({{ $role->id }})" wire:confirm="Delete this role?" class="text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No roles in the database yet — run Sync from code defaults.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                    <h2 class="text-base font-semibold mb-3">{{ $editingRoleId ? 'Edit role' : 'New role' }}</h2>
                    <form wire:submit="saveRole" class="space-y-3">
                        <input wire:model="role_name" type="text" placeholder="ROLE_NAME" class="w-full rounded border-gray-300 shadow-sm font-mono text-sm">
                        @error('role_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        <input wire:model="role_description" type="text" placeholder="Description" class="w-full rounded border-gray-300 shadow-sm text-sm">
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save</button>
                            @if ($editingRoleId)
                                <button wire:click="resetRoleForm" type="button" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                            @endif
                        </div>
                    </form>
                </div>

                @if ($selectedRole)
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200 max-h-96 overflow-y-auto">
                        <h2 class="text-base font-semibold mb-3">Grants — {{ $selectedRole->name }}</h2>
                        @foreach ($permissionsGrouped as $group => $permissions)
                            <div class="mb-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ $group }}</div>
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 text-sm py-0.5">
                                        <input type="checkbox" wire:click="togglePermission({{ $selectedRole->id }}, {{ $permission->id }})"
                                            @checked(in_array($permission->key, $grantedKeys, true))
                                            class="rounded border-gray-300 text-brand-600">
                                        <span class="font-mono">{{ $permission->key }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @elseif ($activeTab === 'permissions')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden max-h-[32rem] overflow-y-auto">
                @foreach ($permissionsGrouped as $group => $permissions)
                    <div class="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-500 uppercase">{{ $group }}</div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($permissions as $permission)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-sm">{{ $permission->key }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $permission->description }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <button wire:click="deletePermission({{ $permission->id }})" wire:confirm="Delete this permission? Grants are removed too." class="text-red-600 hover:underline text-sm">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200 h-fit">
                <h2 class="text-base font-semibold mb-3">New permission</h2>
                <form wire:submit="createPermission" class="space-y-3">
                    <input wire:model="permission_key" type="text" placeholder="module.action" class="w-full rounded border-gray-300 shadow-sm font-mono text-sm">
                    @error('permission_key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <input wire:model="permission_description" type="text" placeholder="Description" class="w-full rounded border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Create</button>
                </form>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-base font-semibold mb-3">Find user</h2>
                <input wire:model.live.debounce.300ms="userSearch" type="text" placeholder="Name or email…" class="w-full rounded border-gray-300 shadow-sm mb-3">
                @forelse ($userResults as $result)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <div class="font-medium text-gray-900 text-sm">{{ $result->name }}</div>
                            <div class="text-xs text-gray-500">{{ $result->email }} · primary: {{ $result->role }}</div>
                        </div>
                        <button wire:click="selectUser({{ $result->id }})" class="text-brand-600 hover:underline text-sm">Assign roles</button>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">Search for a user.</div>
                @endforelse
            </div>
            @if ($selectedUser)
                <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200 h-fit">
                    <h2 class="text-base font-semibold mb-1">Extra roles — {{ $selectedUser->name }}</h2>
                    <p class="text-xs text-gray-500 mb-3">Primary role: {{ $selectedUser->role }} (unchanged here). Extra roles stack permissions on top.</p>
                    @foreach ($allRoles as $role)
                        <label class="flex items-center gap-2 text-sm py-0.5">
                            <input type="checkbox" wire:click="toggleUserRole({{ $selectedUser->id }}, {{ $role->id }})"
                                @checked($selectedUser->rbacRoles->contains('id', $role->id))
                                class="rounded border-gray-300 text-brand-600">
                            <span class="font-mono">{{ $role->name }}</span>
                        </label>
                    @endforeach

                    @php
                        $grants = $selectedUser->permissions['grants'] ?? [];
                        $revokes = $selectedUser->permissions['revokes'] ?? [];
                    @endphp
                    <h3 class="text-sm font-semibold mt-4 mb-1">Individual overrides</h3>
                    <p class="text-xs text-gray-500 mb-2">Allow grants a permission on top of the role; deny removes it even if the role grants it.</p>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-2 py-1">Permission</th>
                                <th class="px-2 py-1 text-center">Allow</th>
                                <th class="px-2 py-1 text-center">Deny</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($permissionsGrouped as $group => $permissions)
                                @foreach ($permissions as $permission)
                                    <tr>
                                        <td class="px-2 py-1 font-mono text-xs">{{ $permission->key }}</td>
                                        <td class="px-2 py-1 text-center">
                                            <input type="checkbox" wire:click="toggleUserPermission({{ $selectedUser->id }}, '{{ $permission->key }}', 'grants')"
                                                @checked(in_array($permission->key, $grants)) class="rounded border-gray-300 text-green-600">
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <input type="checkbox" wire:click="toggleUserPermission({{ $selectedUser->id }}, '{{ $permission->key }}', 'revokes')"
                                                @checked(in_array($permission->key, $revokes)) class="rounded border-gray-300 text-red-600">
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
