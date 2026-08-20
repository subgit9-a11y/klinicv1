<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\SubscriptionManagement;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_view_subscriptions(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SubscriptionManagement::class)
            ->assertStatus(200)
            ->assertSee('Subscriptions');
    }

    public function test_non_super_admin_gets_403(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        Livewire::actingAs($user)
            ->test(SubscriptionManagement::class)
            ->assertStatus(403);
    }

    public function test_activate_creates_active_subscription_and_cancels_predecessor(): void
    {
        $tenant = Tenant::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SubscriptionManagement::class)
            ->set('tenant_id', $tenant->id)
            ->set('plan_code', 'SOLO_DOCTOR')
            ->call('activate');

        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'status' => 'ACTIVE']);

        // Replacing with another plan cancels the first.
        Livewire::actingAs($this->admin())
            ->test(SubscriptionManagement::class)
            ->set('tenant_id', $tenant->id)
            ->set('plan_code', 'SMALL_CLINIC')
            ->call('activate');

        $this->assertSame(1, Subscription::where('tenant_id', $tenant->id)->where('status', 'ACTIVE')->count());
        $this->assertSame(1, Subscription::where('tenant_id', $tenant->id)->where('status', 'CANCELLED')->count());
    }

    public function test_cancel_marks_subscription_cancelled_with_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = app(\App\Services\Plans\PlanService::class)->activate($tenant->id, 'SOLO_DOCTOR');

        Livewire::actingAs($this->admin())
            ->test(SubscriptionManagement::class)
            ->set('cancel_reason', 'Requested by clinic')
            ->call('cancel', $subscription->id);

        $this->assertSame('CANCELLED', $subscription->fresh()->status);
        $this->assertSame('Requested by clinic', $subscription->fresh()->cancel_reason);
    }

    public function test_cancel_requires_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = app(\App\Services\Plans\PlanService::class)->activate($tenant->id, 'SOLO_DOCTOR');

        Livewire::actingAs($this->admin())
            ->test(SubscriptionManagement::class)
            ->call('cancel', $subscription->id)
            ->assertHasErrors(['cancel_reason']);
    }
}
