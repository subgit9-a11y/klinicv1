<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_and_edit_own_appointments(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
        ]);

        $this->assertTrue($doctor->can('view', $appt));
        $this->assertTrue($doctor->can('update', $appt));
        $this->assertTrue($doctor->can('cancel', $appt));
    }

    public function test_receptionist_can_create_appointments(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $receptionist = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $this->assertTrue($receptionist->can('appointments.create'));
        $this->assertTrue($receptionist->can('appointments.view'));
        $this->assertTrue($receptionist->can('queue.manage'));
    }

    public function test_cross_tenant_appointment_access_is_denied(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        app(TenantContext::class)->set($tenantA->id);

        $doctorA = User::factory()->forTenant($tenantA)->role('DOCTOR')->create();
        $doctorB = User::factory()->forTenant($tenantB)->role('DOCTOR')->create();

        $patientA = Patient::factory()->create(['tenant_id' => $tenantA->id]);
        $appt = Appointment::factory()->create([
            'tenant_id' => $tenantA->id,
            'patient_id' => $patientA->id,
            'user_id' => $doctorA->id,
        ]);

        // Doctor B (different tenant) cannot view/edit tenant A's appointment.
        $this->assertFalse($doctorB->can('view', $appt));
        $this->assertFalse($doctorB->can('update', $appt));
        $this->assertFalse($doctorB->can('cancel', $appt));
    }

    public function test_super_admin_can_access_any_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
        ]);

        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue($superAdmin->can('view', $appt));
        $this->assertTrue($superAdmin->can('update', $appt));
        $this->assertTrue($superAdmin->can('cancel', $appt));
    }

    public function test_clinic_owner_can_manage_any_appointment_in_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
        ]);

        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        $this->assertTrue($owner->can('view', $appt));
        $this->assertTrue($owner->can('update', $appt));
        $this->assertTrue($owner->can('cancel', $appt));
    }

    public function test_user_without_edit_permission_can_only_view_not_edit(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        // THERAPIST role has appointments.view but NOT appointments.edit/cancel.
        $therapist = User::factory()->forTenant($tenant)->role('THERAPIST')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
        ]);

        // Therapist may view (has appointments.view) but cannot edit/cancel someone else's appt.
        $this->assertTrue($therapist->can('view', $appt));
        $this->assertFalse($therapist->can('update', $appt));
        $this->assertFalse($therapist->can('cancel', $appt));
    }
}
