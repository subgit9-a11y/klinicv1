<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\UserManagement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_view_users(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->role('DOCTOR')->create(['email' => 'doc@clinic.test']);

        Livewire::actingAs($this->admin())
            ->test(UserManagement::class)
            ->assertStatus(200)
            ->assertSee('doc@clinic.test');
    }

    public function test_non_super_admin_gets_403(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->assertStatus(403);
    }

    public function test_create_user_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(UserManagement::class)
            ->set('name', 'Reception One')
            ->set('email', 'reception@clinic.test')
            ->set('password', 'secret123')
            ->set('tenant_id', $tenant->id)
            ->set('role', 'RECEPTIONIST')
            ->call('createUser');

        $this->assertDatabaseHas('users', [
            'email' => 'reception@clinic.test',
            'tenant_id' => $tenant->id,
            'role' => 'RECEPTIONIST',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    public function test_toggle_active_disables_and_enables(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->role('DOCTOR')->create(['is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(UserManagement::class)
            ->call('toggleActive', $user->id);
        $this->assertFalse($user->fresh()->is_active);

        Livewire::actingAs($this->admin())
            ->test(UserManagement::class)
            ->call('toggleActive', $user->id);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_cannot_disable_own_super_admin_account(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('toggleActive', $admin->id);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_role_filter_limits_list(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->role('DOCTOR')->create(['name' => 'Doctor Who']);
        User::factory()->forTenant($tenant)->role('NURSE')->create(['name' => 'Nurse Nancy']);

        Livewire::actingAs($this->admin())
            ->test(UserManagement::class)
            ->set('roleFilter', 'DOCTOR')
            ->assertSee('Doctor Who')
            ->assertDontSee('Nurse Nancy');
    }
}
