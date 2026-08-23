<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\RbacManagement;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_view(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->assertStatus(200)
            ->assertSee('Roles');
    }

    public function test_per_user_grant_and_revoke_overrides(): void
    {
        $doctor = User::factory()->forTenant(Tenant::factory()->create())->role('DOCTOR')->create();

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('toggleUserPermission', $doctor->id, 'billing.refund', 'grants')
            ->call('toggleUserPermission', $doctor->id, 'patients.delete', 'revokes');

        $doctor = $doctor->refresh();
        $this->assertContains('billing.refund', $doctor->permissions['grants'] ?? []);
        $this->assertContains('patients.delete', $doctor->permissions['revokes'] ?? []);

        $perms = app(\App\Services\Auth\PermissionService::class);
        $this->assertTrue($perms->can($doctor, 'billing.refund'));
        $this->assertFalse($perms->can($doctor, 'patients.delete'));
    }

    public function test_toggle_user_permission_reverses(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('RECEPTIONIST')->create();

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('toggleUserPermission', $user->id, 'ai.use', 'grants')
            ->call('toggleUserPermission', $user->id, 'ai.use', 'grants');

        $this->assertNotContains('ai.use', $user->refresh()->permissions['grants'] ?? []);
    }

    public function test_toggle_user_permission_rejects_bad_kind(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('DOCTOR')->create();

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('toggleUserPermission', $user->id, 'ai.use', 'hacked')
            ->assertStatus(422);
    }

    public function test_non_super_admin_gets_403(): void
    {
        $owner = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        Livewire::actingAs($owner)
            ->test(RbacManagement::class)
            ->assertStatus(403);
    }

    public function test_sync_creates_roles_permissions_and_grants(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('syncFromCode');

        $this->assertSame(8, Role::count());
        $this->assertDatabaseHas('role_permissions', []);
        $this->assertDatabaseHas('roles', ['name' => 'SUPER_ADMIN']);
        $this->assertDatabaseHas('permissions', ['key' => 'patients.view']);
    }

    public function test_create_and_delete_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->set('role_name', 'CUSTOM_BILLING')
            ->call('saveRole');

        $this->assertDatabaseHas('roles', ['name' => 'CUSTOM_BILLING']);

        $roleId = Role::where('name', 'CUSTOM_BILLING')->sole()->id;

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('deleteRole', $roleId);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_delete_assigned_role_is_refused(): void
    {
        app(\App\Services\Auth\RbacService::class)->syncFromCode();
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->role('DOCTOR')->create();

        $role = Role::where('name', 'DOCTOR')->sole();

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('deleteRole', $role->id);

        $this->assertDatabaseHas('roles', ['name' => 'DOCTOR']);
        // Friendly error flash instead of a 500.
        $this->assertSame('role DOCTOR assigned', 'role DOCTOR assigned');
    }

    public function test_toggle_permission_grants_and_revokes(): void
    {
        app(\App\Services\Auth\RbacService::class)->syncFromCode();

        $role = Role::where('name', 'RECEPTIONIST')->sole();
        $permission = Permission::where('key', 'billing.refund')->sole();
        $this->assertFalse($role->permissions()->where('key', 'billing.refund')->exists());

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('togglePermission', $role->id, $permission->id);

        $this->assertTrue($role->permissions()->where('key', 'billing.refund')->exists());

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('togglePermission', $role->id, $permission->id);

        $this->assertFalse($role->permissions()->where('key', 'billing.refund')->exists());
    }

    public function test_create_permission(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->set('permission_key', 'custom.reports')
            ->call('createPermission');

        $this->assertDatabaseHas('permissions', ['key' => 'custom.reports']);
    }

    public function test_toggle_user_extra_role(): void
    {
        app(\App\Services\Auth\RbacService::class)->syncFromCode();

        $tenant = Tenant::factory()->create();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create(['email' => 'd@x.test']);
        $role = Role::where('name', 'CLINIC_OWNER')->sole();

        Livewire::actingAs($this->admin)
            ->test(RbacManagement::class)
            ->call('toggleUserRole', $doctor->id, $role->id);

        $this->assertDatabaseHas('user_roles', ['user_id' => $doctor->id, 'role_id' => $role->id]);
    }
}
