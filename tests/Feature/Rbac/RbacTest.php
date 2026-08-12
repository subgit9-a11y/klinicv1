<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?Tenant $tenant = null): User
    {
        $factory = User::factory()->role($role);

        if ($tenant !== null) {
            $factory = $factory->forTenant($tenant);
        }

        return $factory->create();
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $admin = $this->makeUser('SUPER_ADMIN');
        $service = app(PermissionService::class);

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($service->can($admin, $permission), "super admin should pass $permission");
        }
    }

    public function test_clinic_owner_has_all_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->makeUser('CLINIC_OWNER', $tenant);
        $service = app(PermissionService::class);

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($service->can($owner, $permission), "owner should pass $permission");
        }
    }

    public function test_doctor_can_prescribe_but_not_refund(): void
    {
        $tenant = Tenant::factory()->create();
        $doctor = $this->makeUser('DOCTOR', $tenant);
        $service = app(PermissionService::class);

        $this->assertTrue($service->can($doctor, Permissions::PRESCRIPTIONS_CREATE));
        $this->assertTrue($service->can($doctor, Permissions::CONSULTATIONS_CREATE));
        $this->assertFalse($service->can($doctor, Permissions::BILLING_REFUND));
        $this->assertFalse($service->can($doctor, Permissions::CASH_REGISTER_MANAGE));
        $this->assertFalse($service->can($doctor, Permissions::IPD_DISCHARGE));
    }

    public function test_receptionist_can_manage_queue_and_cash_but_not_prescribe(): void
    {
        $tenant = Tenant::factory()->create();
        $receptionist = $this->makeUser('RECEPTIONIST', $tenant);
        $service = app(PermissionService::class);

        $this->assertTrue($service->can($receptionist, Permissions::QUEUE_MANAGE));
        $this->assertTrue($service->can($receptionist, Permissions::CASH_REGISTER_MANAGE));
        $this->assertTrue($service->can($receptionist, Permissions::BILLING_CREATE));
        $this->assertFalse($service->can($receptionist, Permissions::PRESCRIPTIONS_CREATE));
        $this->assertFalse($service->can($receptionist, Permissions::AI_USE));
    }

    public function test_nurse_can_manage_vitals_but_not_create_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $nurse = $this->makeUser('NURSE', $tenant);
        $service = app(PermissionService::class);

        $this->assertTrue($service->can($nurse, Permissions::VITALS_MANAGE));
        $this->assertTrue($service->can($nurse, Permissions::IPD_NOTES));
        $this->assertFalse($service->can($nurse, Permissions::CONSULTATIONS_CREATE));
        $this->assertFalse($service->can($nurse, Permissions::PRESCRIPTIONS_CREATE));
    }

    public function test_ipd_staff_can_admit_and_discharge(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = $this->makeUser('IPD_STAFF', $tenant);
        $service = app(PermissionService::class);

        $this->assertTrue($service->can($staff, Permissions::IPD_ADMIT));
        $this->assertTrue($service->can($staff, Permissions::IPD_DISCHARGE));
        $this->assertFalse($service->can($staff, Permissions::PRESCRIPTIONS_CREATE));
    }

    public function test_per_user_grants_add_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $assistant = $this->makeUser('ASSISTANT', $tenant);

        // Assistant cannot refund by default.
        $service = app(PermissionService::class);
        $this->assertFalse($service->can($assistant, Permissions::BILLING_REFUND));

        $assistant->permissions = ['grants' => [Permissions::BILLING_REFUND], 'revokes' => []];
        $assistant->save();

        $this->assertTrue($service->can($assistant->fresh(), Permissions::BILLING_REFUND));
    }

    public function test_per_user_revokes_remove_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $doctor = $this->makeUser('DOCTOR', $tenant);
        $service = app(PermissionService::class);

        $this->assertTrue($service->can($doctor, Permissions::PRESCRIPTIONS_CREATE));

        $doctor->permissions = ['grants' => [], 'revokes' => [Permissions::PRESCRIPTIONS_CREATE]];
        $doctor->save();

        $this->assertFalse($service->can($doctor->fresh(), Permissions::PRESCRIPTIONS_CREATE));
    }

    public function test_gate_delegates_to_permission_service(): void
    {
        $tenant = Tenant::factory()->create();
        $doctor = $this->makeUser('DOCTOR', $tenant);
        $receptionist = $this->makeUser('RECEPTIONIST', $tenant);

        $this->assertTrue(Gate::forUser($doctor)->allows(Permissions::PRESCRIPTIONS_CREATE));
        $this->assertFalse(Gate::forUser($receptionist)->allows(Permissions::PRESCRIPTIONS_CREATE));
    }

    public function test_user_has_permission_helper_methods(): void
    {
        $tenant = Tenant::factory()->create();
        $doctor = $this->makeUser('DOCTOR', $tenant);

        $this->assertTrue($doctor->hasPermission(Permissions::CONSULTATIONS_CREATE));
        $this->assertFalse($doctor->hasPermission(Permissions::BILLING_REFUND));

        $this->assertTrue($doctor->hasAnyPermission([Permissions::BILLING_REFUND, Permissions::PRESCRIPTIONS_CREATE]));
        $this->assertFalse($doctor->hasAnyPermission([Permissions::BILLING_REFUND, Permissions::CASH_REGISTER_MANAGE]));

        $this->assertTrue($doctor->hasAllPermissions([Permissions::CONSULTATIONS_CREATE, Permissions::VITALS_MANAGE]));
        $this->assertFalse($doctor->hasAllPermissions([Permissions::CONSULTATIONS_CREATE, Permissions::BILLING_REFUND]));
    }

    public function test_super_admin_via_gate(): void
    {
        $admin = $this->makeUser('SUPER_ADMIN');

        $this->assertTrue(Gate::forUser($admin)->allows(Permissions::BILLING_REFUND));
        $this->assertTrue(Gate::forUser($admin)->allows(Permissions::AI_PROMPTS_MANAGE));
    }

    public function test_permissions_catalog_covers_all_modules(): void
    {
        $this->assertNotEmpty(Permissions::patients());
        $this->assertNotEmpty(Permissions::appointments());
        $this->assertNotEmpty(Permissions::consultations());
        $this->assertNotEmpty(Permissions::prescriptions());
        $this->assertNotEmpty(Permissions::treatments());
        $this->assertNotEmpty(Permissions::ipd());
        $this->assertNotEmpty(Permissions::billing());
        $this->assertNotEmpty(Permissions::ai());
        $this->assertNotEmpty(Permissions::documents());

        // Every grouped permission must be in all().
        $all = Permissions::all();
        foreach (Permissions::grouped() as $perms) {
            foreach ($perms as $p) {
                $this->assertContains($p, $all);
            }
        }
    }
}
