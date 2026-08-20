<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\OperationsCenter;
use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\PaymentWebhook;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_view_audit_tab(): void
    {
        AuditLog::create(['action' => 'tenant.created', 'category' => 'TENANCY', 'after' => ['x' => 1]]);

        Livewire::actingAs($this->admin())
            ->test(OperationsCenter::class)
            ->assertStatus(200)
            ->assertSee('tenant.created');
    }

    public function test_non_super_admin_gets_403(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        Livewire::actingAs($user)
            ->test(OperationsCenter::class)
            ->assertStatus(403);
    }

    public function test_webhook_tab_shows_received_webhooks(): void
    {
        PaymentWebhook::create([
            'gateway' => 'CASHFREE',
            'event_id' => 'ORD-1::order::PAYMENT_STATUS',
            'event_type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'gateway_order_id' => 'ORD-1',
            'payload' => [],
            'processed' => true,
            'processed_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(OperationsCenter::class)
            ->call('setTab', 'webhooks')
            ->assertSee('PAYMENT_SUCCESS_WEBHOOK')
            ->assertSee('ORD-1');
    }

    public function test_notifications_tab_shows_deliveries(): void
    {
        $tenant = Tenant::factory()->create();
        app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);
        $patient = \App\Models\Patient::factory()->create(['tenant_id' => $tenant->id]);
        app(\App\Services\Tenancy\TenantContext::class)->forget();

        NotificationDelivery::create([
            'tenant_id' => $tenant->id,
            'notifiable_type' => \App\Models\Patient::class,
            'notifiable_id' => $patient->id,
            'channel' => 'SMS',
            'recipient' => '+919900001111',
            'status' => 'FAILED',
            'attempts' => 3,
        ]);

        Livewire::actingAs($this->admin())
            ->test(OperationsCenter::class)
            ->call('setTab', 'notifications')
            ->assertSee('+919900001111')
            ->assertSee('FAILED');
    }

    public function test_audit_search_filters_results(): void
    {
        AuditLog::create(['action' => 'auth.login', 'category' => 'AUTH']);
        AuditLog::create(['action' => 'tenant.suspended', 'category' => 'TENANCY']);

        Livewire::actingAs($this->admin())
            ->test(OperationsCenter::class)
            ->set('search', 'tenant.suspended')
            ->assertSee('tenant.suspended')
            ->assertDontSee('auth.login');
    }
}
