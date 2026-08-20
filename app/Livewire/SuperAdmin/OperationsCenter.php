<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhook;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Super Admin operations viewer: audit trail, payment webhooks, notification
 * deliveries and payment orders across ALL tenants (read-only).
 */
class OperationsCenter extends Component
{
    use WithPagination;

    public string $activeTab = 'audit';

    public string $search = '';

    public ?int $tenantFilter = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);

        $data = match ($this->activeTab) {
            'webhooks' => [
                'rows' => PaymentWebhook::query()
                    ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('gateway_order_id', 'like', "%{$this->search}%")->orWhere('event_type', 'like', "%{$this->search}%")))
                    ->when($this->tenantFilter !== null, fn ($q) => $q->where('tenant_id', $this->tenantFilter))
                    ->latest()->paginate(15),
            ],
            'notifications' => [
                'rows' => NotificationDelivery::query()
                    ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('recipient', 'like', "%{$this->search}%")->orWhere('channel', 'like', "%{$this->search}%")))
                    ->when($this->tenantFilter !== null, fn ($q) => $q->where('tenant_id', $this->tenantFilter))
                    ->latest()->paginate(15),
            ],
            'orders' => [
                'rows' => PaymentOrder::query()
                    ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('gateway_order_id', 'like', "%{$this->search}%")->orWhere('internal_order_id', 'like', "%{$this->search}%")))
                    ->when($this->tenantFilter !== null, fn ($q) => $q->where('tenant_id', $this->tenantFilter))
                    ->latest()->paginate(15),
            ],
            default => [
                'rows' => AuditLog::query()
                    ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('action', 'like', "%{$this->search}%")->orWhere('category', 'like', "%{$this->search}%")))
                    ->when($this->tenantFilter !== null, fn ($q) => $q->where('tenant_id', $this->tenantFilter))
                    ->latest()->paginate(15),
            ],
        };

        return view('livewire.super-admin.operations-center', array_merge($data, [
            'tenants' => \App\Models\Tenant::orderBy('name')->get(['id', 'name']),
        ]))->layout('components.layouts.app');
    }
}
