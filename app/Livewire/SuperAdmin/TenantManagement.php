<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Tenancy\TenantAdminService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Super Admin clinic control plane: list/create/edit/suspend/activate/archive
 * tenants. All mutations go through TenantAdminService (audit-logged).
 */
class TenantManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    // Clinic form
    public string $name = '';

    public string $plan_code = 'SOLO_DOCTOR';

    public string $system = 'AYURVEDA';

    public string $email = '';

    public string $phone = '';

    public string $owner_name = '';

    public string $owner_email = '';

    public string $owner_password = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'plan_code' => 'required|string|exists:plans,code',
            'system' => 'required|in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:20',
            'owner_name' => $this->editingId ? 'nullable|string|max:120' : 'nullable|string|max:120',
            'owner_email' => 'nullable|email|max:190|unique:users,email',
            'owner_password' => $this->owner_email ? 'required|string|min:8' : 'nullable',
        ];
    }

    public function createClinic(TenantAdminService $admin): void
    {
        $this->guardSuperAdmin();

        $data = $this->validate();

        $tenant = $admin->createClinic([
            'name' => $this->name,
            'plan_code' => $this->plan_code,
            'system' => $this->system,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'owner_name' => $this->owner_name ?: null,
            'owner_email' => $this->owner_email ?: null,
            'owner_password' => $this->owner_password ?: null,
        ]);

        session()->flash('message', "Clinic {$tenant->name} created.");
        $this->resetForm();
    }

    public function edit(int $tenantId): void
    {
        $this->guardSuperAdmin();

        $tenant = Tenant::findOrFail($tenantId);
        $this->editingId = $tenant->id;
        $this->name = $tenant->name;
        $this->plan_code = $tenant->plan_code;
        $this->system = $tenant->system;
        $this->email = (string) $tenant->email;
        $this->phone = (string) $tenant->phone;
        $this->showForm = true;
    }

    public function saveEdit(TenantAdminService $admin): void
    {
        $this->guardSuperAdmin();

        $tenant = Tenant::findOrFail($this->editingId);
        $admin->update($tenant, $this->validate());

        session()->flash('message', "Clinic {$tenant->name} updated.");
        $this->resetForm();
    }

    public function suspend(int $tenantId, TenantAdminService $admin): void
    {
        $this->guardSuperAdmin();

        $tenant = $admin->suspend(Tenant::findOrFail($tenantId));
        session()->flash('message', "Clinic {$tenant->name} suspended.");
    }

    public function activate(int $tenantId, TenantAdminService $admin): void
    {
        $this->guardSuperAdmin();

        $tenant = $admin->activate(Tenant::findOrFail($tenantId));
        session()->flash('message', "Clinic {$tenant->name} activated.");
    }

    public function archive(int $tenantId, TenantAdminService $admin): void
    {
        $this->guardSuperAdmin();

        $tenant = Tenant::findOrFail($tenantId);
        $name = $tenant->name;
        $admin->archive($tenant);
        session()->flash('message', "Clinic {$name} archived.");
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'email', 'phone', 'owner_name', 'owner_email', 'owner_password', 'editingId']);
        $this->plan_code = 'SOLO_DOCTOR';
        $this->system = 'AYURVEDA';
        $this->showForm = false;
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
    }

    public function render()
    {
        $this->guardSuperAdmin();

        $tenants = Tenant::query()
            ->withCount(['users', 'patients'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        return view('livewire.super-admin.tenant-management', [
            'tenants' => $tenants,
            'plans' => Plan::where('is_active', true)->orderBy('price_cents')->get(),
        ])->layout('components.layouts.app');
    }
}
