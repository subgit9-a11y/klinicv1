<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DB-backed RBAC resolution: the DB wins once synced; the code catalog
 * remains the pre-sync fallback.
 */
class RbacDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsynced_install_falls_back_to_code_catalog(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        $this->assertFalse(app(RbacService::class)->isSynced());
        $this->assertTrue($owner->hasPermission('patients.view'));
    }

    public function test_synced_db_is_source_of_truth(): void
    {
        app(RbacService::class)->syncFromCode();

        $tenant = Tenant::factory()->create();
        $recep = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        // RECEPTIONIST per code catalog lacks billing.refund. In DB we now
        // cut its grants entirely and give it billing.refund to prove the
        // DB (not the code catalog) is authoritative.
        $role = Role::where('name', 'RECEPTIONIST')->sole();
        $role->permissions()->sync([
            Permission::where('key', 'billing.refund')->sole()->id,
        ]);

        $this->assertTrue($recep->hasPermission('billing.refund'));
        $this->assertFalse($recep->hasPermission('patients.view'));
    }

    public function test_extra_role_stacks_permissions_via_user_roles(): void
    {
        $rbac = app(RbacService::class);
        $rbac->syncFromCode();

        $tenant = Tenant::factory()->create();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();

        // DOCTOR lacks billing.view; add CLINIC_OWNER as an extra DB role.
        $doctor->rbacRoles()->attach(Role::where('name', 'CLINIC_OWNER')->sole()->id);

        $this->assertTrue($doctor->fresh()->hasPermission('billing.view'));
    }

    public function test_role_edit_flushes_cached_permissions(): void
    {
        app(RbacService::class)->syncFromCode();

        $tenant = Tenant::factory()->create();
        $recep = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $this->assertFalse($recep->hasPermission('billing.refund'));

        $role = Role::where('name', 'RECEPTIONIST')->sole();
        $role->permissions()->attach(Permission::where('key', 'billing.refund')->sole()->id);

        app(PermissionService::class)->flushRole('RECEPTIONIST');
        $this->assertTrue($recep->fresh()->hasPermission('billing.refund'));
    }

    public function test_super_admin_bypass_is_unchanged(): void
    {
        app(RbacService::class)->syncFromCode();

        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($admin->hasPermission('anything.at.all'));
    }
}
