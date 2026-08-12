<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenantAndUser(): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create([
            'email_verified_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    public function test_appointment_board_renders_for_authenticated_owner(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->assertStatus(200)
            ->assertSee(__('klinic360.appointments.title'));
    }

    public function test_appointment_board_lists_appointments_for_selected_date(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create(['name' => 'Dr. House']);
        $today = now()->format('Y-m-d');

        \App\Models\Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'IN_PERSON',
            'status' => 'SCHEDULED',
            'appointment_date' => $today,
            'start_time' => '10:00',
            'end_time' => '10:30',
            'duration_minutes' => 30,
            'reason' => 'Demo visit',
        ]);

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->set('date', $today)
            ->assertSee('10:00')
            ->assertSee($patient->first_name)
            ->assertSee('Dr. House')
            ->assertSee('SCHEDULED')
            ->assertDontSee(__('klinic360.appointments.none'));
    }

    public function test_booking_form_toggles(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->call('toggleBookingForm')
            ->assertSet('showBookingForm', true)
            ->assertSee(__('klinic360.appointments.book'))
            ->call('toggleBookingForm')
            ->assertSet('showBookingForm', false);
    }

    public function test_book_creates_appointment_via_form(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->set('patientId', $patient->id)
            ->set('doctorId', $doctor->id)
            ->set('type', 'IN_PERSON')
            ->set('date', now()->addDay()->format('Y-m-d'))
            ->set('startTime', '10:00')
            ->set('durationMinutes', 30)
            ->set('reason', 'Consultation')
            ->call('book')
            ->assertHasNoErrors()
            ->assertDispatched('appointment-booked');

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'IN_PERSON',
            'status' => 'SCHEDULED',
        ]);
    }

    public function test_walk_in_booking_creates_token(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->set('patientId', $patient->id)
            ->set('doctorId', $doctor->id)
            ->set('type', 'WALK_IN')
            ->set('date', now()->addDay()->format('Y-m-d'))
            ->set('startTime', '10:00')
            ->set('durationMinutes', 15)
            ->call('book')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointment_tokens', [
            'tenant_id' => $tenant->id,
            'token_number' => '001',
            'status' => 'WAITING',
        ]);
    }

    public function test_double_booking_shows_validation_error(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $date = now()->addDay()->format('Y-m-d');

        $lw = Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class);

        // First booking succeeds.
        $lw->set('patientId', $patient->id)
            ->set('doctorId', $doctor->id)
            ->set('type', 'IN_PERSON')
            ->set('date', $date)
            ->set('startTime', '10:00')
            ->set('durationMinutes', 30)
            ->call('book')
            ->assertHasNoErrors();

        // Overlapping booking fails with start_time error.
        $lw->set('patientId', $patient->id)
            ->set('doctorId', $doctor->id)
            ->set('type', 'IN_PERSON')
            ->set('date', $date)
            ->set('startTime', '10:15')
            ->set('durationMinutes', 30)
            ->call('book')
            ->assertHasErrors(['start_time']);
    }

    public function test_cancel_appointment_via_board(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();

        $appt = \App\Models\Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'status' => 'SCHEDULED',
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'duration_minutes' => 30,
        ]);

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->set('date', $appt->appointment_date->format('Y-m-d'))
            ->call('cancelAppointment', $appt->id);

        $this->assertSame('CANCELLED', $appt->fresh()->status);
        $this->assertNotNull($appt->fresh()->cancelled_at);
    }

    public function test_unauthenticated_user_cannot_access_board(): void
    {
        $response = $this->get('/appointments');
        $response->assertRedirect('/login');
    }

    public function test_receptionist_can_book_appointments(): void
    {
        [$tenant, $owner] = $this->seedTenantAndUser();
        $receptionist = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($receptionist)
            ->test(\App\Livewire\Appointments\AppointmentBoard::class)
            ->set('patientId', $patient->id)
            ->set('doctorId', $doctor->id)
            ->set('type', 'IN_PERSON')
            ->set('date', now()->addDay()->format('Y-m-d'))
            ->set('startTime', '11:00')
            ->set('durationMinutes', 15)
            ->call('book')
            ->assertHasNoErrors();
    }
}
