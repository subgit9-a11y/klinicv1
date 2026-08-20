<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plans\PlanService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Super Admin SaaS subscription control plane: view all subscriptions,
 * (re)activate a clinic on a plan, cancel subscriptions.
 */
class SubscriptionManagement extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public ?int $tenantFilter = null;

    // Activate form
    public bool $showForm = false;

    public ?int $tenant_id = null;

    public string $plan_code = 'SOLO_DOCTOR';

    public string $cancel_reason = '';

    public function activate(PlanService $plans): void
    {
        $this->guardSuperAdmin();

        $this->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_code' => 'required|string|exists:plans,code',
        ]);

        $subscription = $plans->activate($this->tenant_id, $this->plan_code);

        session()->flash('message', "Subscription activated ({$subscription->plan?->name}).");
        $this->reset(['tenant_id', 'showForm']);
        $this->plan_code = 'SOLO_DOCTOR';
    }

    public function cancel(int $subscriptionId, PlanService $plans): void
    {
        $this->guardSuperAdmin();

        $this->validate(['cancel_reason' => 'required|string|max:255']);

        $subscription = Subscription::findOrFail($subscriptionId);
        $plans->cancel($subscription, $this->cancel_reason);

        session()->flash('message', "Subscription #{$subscription->id} cancelled.");
        $this->reset(['cancel_reason']);
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
    }

    public function render()
    {
        $this->guardSuperAdmin();

        $subscriptions = Subscription::query()
            ->with(['tenant:id,name', 'plan:id,name,code,price_cents,currency'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->tenantFilter !== null, fn ($q) => $q->where('tenant_id', $this->tenantFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.super-admin.subscription-management', [
            'subscriptions' => $subscriptions,
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
            'plans' => Plan::where('is_active', true)->orderBy('price_cents')->get(),
        ])->layout('components.layouts.app');
    }
}
