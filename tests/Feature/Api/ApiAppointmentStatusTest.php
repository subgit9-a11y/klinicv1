<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function tokenHeader(User $user): array
    {
        $issued = app(TokenService::class)->create($user, 'test', ['*']);

        return ['Authorization' => 'Bearer '.$issued['token']];
    }

    public function test_can_confirm_scheduled_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
        ]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONFIRMED'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'CONFIRMED');
    }

    public function test_can_check_in_and_complete_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
        ]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CHECKED_IN'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'CHECKED_IN');

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'IN_CONSULTATION'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'IN_CONSULTATION');

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'COMPLETED'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        // COMPLETED is terminal — cannot transition anywhere
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'COMPLETED',
        ]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CHECKED_IN'])
            ->assertStatus(422);
    }

    public function test_can_mark_no_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
        ]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->postJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'NO_SHOW'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'NO_SHOW');
    }

    public function test_can_reschedule_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'duration_minutes' => 30,
        ]);

        $newDate = now()->addDays(3)->toDateString();

        $this->withHeaders($this->tokenHeader($receptionist))
            ->putJson("/api/v1/appointments/{$appointment->id}/reschedule", [
                'appointment_date' => $newDate,
                'start_time' => '14:00',
                'duration_minutes' => 45,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.appointment_date', $newDate)
            ->assertJsonPath('data.start_time', '14:00');
    }

    public function test_reschedule_validates_past_date(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
        ]);

        $this->withHeaders($this->tokenHeader($receptionist))
            ->putJson("/api/v1/appointments/{$appointment->id}/reschedule", [
                'appointment_date' => '2020-01-01',
                'start_time' => '10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['appointment_date']);
    }

    public function test_can_fetch_available_slots(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $date = now()->addDay();
        // Seed one day of availability using the service's 3-letter day code
        \App\Models\DoctorAvailability::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'day_of_week' => ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][(int) $date->format('w')],
            'is_active' => true,
        ]);

        $this->withHeaders($this->tokenHeader($doctor))
            ->getJson('/api/v1/appointments/slots?doctor_id='.$doctor->id.'&date='.$date->toDateString())
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    }
}
