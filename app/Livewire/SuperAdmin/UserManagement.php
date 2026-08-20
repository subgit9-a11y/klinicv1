<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Super Admin cross-tenant user management: list, filter, create users for
 * any clinic, change role, enable/disable accounts.
 */
class UserManagement extends Component
{
    use WithPagination;

    public const ROLES = ['CLINIC_OWNER', 'DOCTOR', 'RECEPTIONIST', 'THERAPIST', 'NURSE', 'IPD_STAFF', 'ASSISTANT'];

    public string $search = '';

    public string $roleFilter = '';

    public ?int $tenantFilter = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    // User form
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $tenant_id = null;

    public string $role = 'RECEPTIONIST';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => $this->editingId ? 'nullable|string|min:8' : 'required|string|min:8',
            'tenant_id' => 'required|exists:tenants,id',
            'role' => ['required', Rule::in(self::ROLES)],
        ];
    }

    public function createUser(AuditService $audit): void
    {
        $this->guardSuperAdmin();
        $this->validate();

        $user = User::create([
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'role' => $this->role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $audit->record('user.created', 'STAFF', ['after' => [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
        ]], $user);

        session()->flash('message', "User {$user->email} created.");
        $this->resetForm();
    }

    public function edit(int $userId): void
    {
        $this->guardSuperAdmin();

        $user = User::findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->tenant_id = $user->tenant_id;
        $this->role = $user->role;
        $this->password = '';
        $this->showForm = true;
    }

    public function saveEdit(AuditService $audit): void
    {
        $this->guardSuperAdmin();
        $this->validate();

        $user = User::findOrFail($this->editingId);
        $before = $user->only(['name', 'email', 'tenant_id', 'role']);

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'role' => $this->role,
        ] + ($this->password !== '' ? ['password' => bcrypt($this->password)] : []));

        $audit->record('user.updated', 'STAFF', [
            'before' => $before,
            'after' => $user->fresh()->only(['name', 'email', 'tenant_id', 'role']),
        ], $user);

        session()->flash('message', "User {$user->email} updated.");
        $this->resetForm();
    }

    public function toggleActive(int $userId, AuditService $audit): void
    {
        $this->guardSuperAdmin();

        $user = User::findOrFail($userId);

        if ($user->isSuperAdmin() && $user->id === auth()->id()) {
            session()->flash('error', 'You cannot disable your own Super Admin account.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $audit->record($user->is_active ? 'user.activated' : 'user.deactivated', 'STAFF', ['after' => [
            'user_id' => $user->id,
        ]], $user);

        session()->flash('message', $user->is_active ? "User {$user->email} enabled." : "User {$user->email} disabled.");
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'email', 'password', 'tenant_id', 'editingId']);
        $this->role = 'RECEPTIONIST';
        $this->showForm = false;
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
    }

    public function render()
    {
        $this->guardSuperAdmin();

        $users = User::query()
            ->with('tenant:id,name')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->roleFilter !== '', fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->tenantFilter !== null, fn ($q) => $q->where('tenant_id', $this->tenantFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.super-admin.user-management', [
            'users' => $users,
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
        ])->layout('components.layouts.app');
    }
}
