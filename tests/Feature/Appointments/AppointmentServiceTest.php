<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentToken;
use App\Models\DoctorAvailability;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function doctor(Tenant $tenant): User
    {
        return User::factory()->forTenant($tenant)->role('DOCTOR')->create();
    }

    private function patient(Tenant $tenant): Patient
    {
        return Patient::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_book_creates_appointment_scoped_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $appt = app(AppointmentService::class)->book([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'IN_PERSON',
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'reason' => 'Follow-up',
        ], $creator);

        $this->assertSame($tenant->id, $appt->tenant_id);
        $this->assertSame('SCHEDULED', $appt->status);
        $this->assertSame('10:30', $appt->end_time);
        $this->assertSame($creator->id, $appt->created_by);
        $this->assertDatabaseHas('appointment_status_history', [
            'appointment_id' => $appt->id,
            'status' => 'SCHEDULED',
        ]);
    }

    public function test_book_blocks_double_booking_for_same_doctor_slot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $service->book([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'IN_PERSON',
            'appointment_date' => $date,
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ], $creator);

        // Overlapping slot (10:15–10:45) must collide with the 10:00–10:30 booking.
        $this->expectException(ValidationException::class);
        $service->book([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'IN_PERSON',
            'appointment_date' => $date,
            'start_time' => '10:15',
            'duration_minutes' => 30,
        ], $creator);
    }

    public function test_book_allows_back_to_back_non_overlapping_slots(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);
        // 10:30 start touches the previous 10:00–10:30 only at the boundary (end == start).
        $appt2 = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:30', 'duration_minutes' => 30,
        ], $creator);

        $this->assertNotNull($appt2->id);
    }

    public function test_book_allows_overlapping_slots_for_different_doctors(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctorA = $this->doctor($tenant);
        $doctorB = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctorA->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);

        $apptB = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctorB->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:15', 'duration_minutes' => 30,
        ], $creator);

        $this->assertNotNull($apptB->id);
    }

    public function test_walk_in_booking_auto_issues_queue_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $appt = app(AppointmentService::class)->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'WALK_IN',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 15,
        ], $creator);

        $this->assertNotNull($appt->token);
        $this->assertSame('001', $appt->token->token_number);
        $this->assertSame('WAITING', $appt->token->status);

        // Second walk-in token for same doctor/date increments to 002.
        $appt2 = app(AppointmentService::class)->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'WALK_IN',
            'appointment_date' => $date, 'start_time' => '11:00', 'duration_minutes' => 15,
        ], $creator);

        $this->assertSame('002', $appt2->token->token_number);
    }

    public function test_non_walk_in_booking_does_not_issue_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $appt = app(AppointmentService::class)->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);

        $this->assertNull($appt->token);
    }

    public function test_status_transition_valid_flow(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'user_id' => $doctor->id,
            'status' => 'SCHEDULED', 'appointment_date' => $date,
            'start_time' => '10:00', 'end_time' => '10:30', 'duration_minutes' => 30,
        ]);

        $appt = $service->changeStatus($appt, 'CONFIRMED', $doctor);
        $this->assertSame('CONFIRMED', $appt->status);

        $appt = $service->changeStatus($appt, 'CHECKED_IN', $doctor);
        $this->assertNotNull($appt->checked_in_at);

        $appt = $service->changeStatus($appt, 'IN_CONSULTATION', $doctor);
        $this->assertSame('IN_CONSULTATION', $appt->status);

        $appt = $service->changeStatus($appt, 'COMPLETED', $doctor);
        $this->assertSame('COMPLETED', $appt->status);
        $this->assertNotNull($appt->completed_at);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);

        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'user_id' => $doctor->id,
            'status' => 'SCHEDULED', 'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00', 'end_time' => '10:30', 'duration_minutes' => 30,
        ]);

        $this->expectException(ValidationException::class);
        // Cannot jump from SCHEDULED straight to COMPLETED.
        app(AppointmentService::class)->changeStatus($appt, 'COMPLETED', $doctor);
    }

    public function test_cancel_sets_reason_and_timestamp(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);

        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'user_id' => $doctor->id,
            'status' => 'SCHEDULED', 'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00', 'end_time' => '10:30', 'duration_minutes' => 30,
        ]);

        $appt = app(AppointmentService::class)->cancel($appt, $doctor, 'Patient unavailable');

        $this->assertSame('CANCELLED', $appt->status);
        $this->assertNotNull($appt->cancelled_at);
        $this->assertSame('Patient unavailable', $appt->cancellation_reason);
    }

    public function test_cancel_blocked_for_completed_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);

        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'user_id' => $doctor->id,
            'status' => 'COMPLETED', 'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00', 'end_time' => '10:30', 'duration_minutes' => 30,
        ]);

        $this->expectException(ValidationException::class);
        app(AppointmentService::class)->cancel($appt, $doctor);
    }

    public function test_cancelled_appointment_does_not_block_rebooking_slot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $appt = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);
        $service->cancel($appt, $creator);

        // Re-booking the now-free slot must succeed.
        $rebooked = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);

        $this->assertNotNull($rebooked->id);
    }

    public function test_reschedule_moves_appointment_and_prevents_collision(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $a = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);
        $b = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '11:00', 'duration_minutes' => 30,
        ], $creator);

        // Rescheduling A onto B's slot must collide.
        $this->expectException(ValidationException::class);
        $service->reschedule($a, [
            'appointment_date' => $date, 'start_time' => '11:00', 'duration_minutes' => 30,
        ], $creator);
    }

    public function test_reschedule_to_free_slot_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');

        $service = app(AppointmentService::class);
        $appt = $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30,
        ], $creator);

        $moved = $service->reschedule($appt, [
            'appointment_date' => $date, 'start_time' => '14:00', 'duration_minutes' => 30,
        ], $creator);

        $this->assertSame('14:00', $moved->start_time);
        $this->assertSame('14:30', $moved->end_time);
    }

    public function test_available_slots_respects_doctor_availability_and_bookings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $patient = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $date = now()->addDay()->format('Y-m-d');
        $dayCode = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][(int) date('w', strtotime($date))];

        DoctorAvailability::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $doctor->id,
            'day_of_week' => $dayCode, 'start_time' => '09:00', 'end_time' => '09:45', 'is_active' => true,
        ]);

        $service = app(AppointmentService::class);
        // Book the 09:00–09:15 slot.
        $service->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'IN_PERSON',
            'appointment_date' => $date, 'start_time' => '09:00', 'duration_minutes' => 15,
        ], $creator);

        $slots = $service->availableSlots($doctor, $date, 15);

        // Three slots: 09:00 (booked), 09:15 (free), 09:30 (free).
        $this->assertCount(3, $slots);
        $this->assertFalse($slots[0]['available']); // 09:00
        $this->assertTrue($slots[1]['available']);  // 09:15
        $this->assertTrue($slots[2]['available']);   // 09:30
    }

    public function test_available_slots_empty_when_doctor_unavailable_that_day(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);

        $slots = app(AppointmentService::class)->availableSlots($doctor, now()->addDay()->format('Y-m-d'));

        $this->assertSame([], $slots);
    }

    public function test_for_patient_returns_appointments_for_that_patient_only(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $p1 = $this->patient($tenant);
        $p2 = $this->patient($tenant);

        Appointment::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $p1->id, 'user_id' => $doctor->id]);
        Appointment::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $p2->id, 'user_id' => $doctor->id]);

        $result = app(AppointmentService::class)->forPatient($p1);
        $this->assertCount(1, $result);
        $this->assertSame($p1->id, $result->first()->patient_id);
    }

    public function test_appointments_are_tenant_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $doctorA = $this->doctor($tenantA);
        $pA = $this->patient($tenantA);

        Appointment::factory()->create(['tenant_id' => $tenantA->id, 'patient_id' => $pA->id, 'user_id' => $doctorA->id]);

        // From tenant B's context, tenant A's appointments are invisible (global scope).
        $this->setTenant($tenantB);
        $this->assertSame(0, Appointment::count());
    }

    public function test_booking_requires_tenant_context(): void
    {
        app(TenantContext::class)->forget();
        $this->expectException(\RuntimeException::class);
        app(AppointmentService::class)->book([
            'patient_id' => 1, 'user_id' => 1, 'type' => 'IN_PERSON',
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00', 'duration_minutes' => 30,
        ], User::factory()->make());
    }
}
