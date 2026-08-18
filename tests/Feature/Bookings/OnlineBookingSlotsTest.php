<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Models\DoctorAvailability;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the public /book/slots endpoint returns real bookable slots from
 * the AppointmentService engine, scoped to the resolved public-booking tenant
 * and only active practitioners — no cross-tenant slot enumeration.
 */
class OnlineBookingSlotsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\SystemSettingsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_slots_endpoint_returns_bookable_slots_for_doctor_on_date(): void
    {
        $date = now()->addDay();
        $dayCode = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][(int) $date->format('w')];

        DoctorAvailability::factory()->create([
            'user_id' => $this->doctor->id,
            'day_of_week' => $dayCode,
            'start_time' => '09:00',
            'end_time' => '09:45',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $this->doctor->id,
            'date' => $date->toDateString(),
        ]));

        $response->assertOk();
        $slots = $response->json('data');
        $this->assertIsArray($slots);
        // 45 minutes / 15-min slots = 3 slots, all available.
        $this->assertCount(3, $slots);
        $this->assertSame('09:00', $slots[0]['start']);
        $this->assertSame('09:15', $slots[1]['start']);
        $this->assertSame('09:30', $slots[2]['start']);
        foreach ($slots as $slot) {
            $this->asserttrue($slot['available']);
        }
    }

    public function test_slots_endpoint_marks_overlapping_booked_slots_unavailable(): void
    {
        $date = now()->addDay();
        $dayCode = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][(int) $date->format('w')];

        DoctorAvailability::factory()->create([
            'user_id' => $this->doctor->id,
            'day_of_week' => $dayCode,
            'start_time' => '09:00',
            'end_time' => '09:45',
            'is_active' => true,
        ]);

        // Book the 09:15 slot so it should come back unavailable.
        $patient = \App\Models\Patient::factory()->create();
        \App\Models\Appointment::factory()->create([
            'user_id' => $this->doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '09:15',
            'end_time' => '09:45',
            'status' => 'SCHEDULED',
        ]);

        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $this->doctor->id,
            'date' => $date->toDateString(),
        ]));

        $slots = collect($response->json('data'))->keyBy('start');
        $this->assertTrue($slots['09:00']['available']);
        $this->assertFalse($slots['09:15']['available']);
        // 09:30-09:45 also overlaps the 09:15-09:45 booking, so it's unavailable too.
        $this->assertFalse($slots['09:30']['available']);
    }

    public function test_slots_endpoint_returns_empty_when_no_working_hours(): void
    {
        $date = now()->addDay();

        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $this->doctor->id,
            'date' => $date->toDateString(),
        ]));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_slots_endpoint_rejects_cross_tenant_doctor(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherDoctor = User::factory()->forTenant($otherTenant)->role('DOCTOR')->create();

        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $otherDoctor->id,
            'date' => now()->addDay()->toDateString(),
        ]));

        $response->assertNotFound();
    }

    public function test_slots_endpoint_rejects_inactive_doctor(): void
    {
        $inactive = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create(['is_active' => false]);

        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $inactive->id,
            'date' => now()->addDay()->toDateString(),
        ]));

        $response->assertNotFound();
    }

    public function test_slots_endpoint_rejects_non_practitioner_role(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $receptionist->id,
            'date' => now()->addDay()->toDateString(),
        ]));

        $response->assertNotFound();
    }

    public function test_slots_endpoint_validates_required_fields(): void
    {
        $response = $this->getJson(route('online-booking.slots'));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'date']);
    }

    public function test_slots_endpoint_rejects_past_date(): void
    {
        $response = $this->getJson(route('online-booking.slots', [
            'user_id' => $this->doctor->id,
            'date' => now()->subDay()->toDateString(),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['date']);
    }

    public function test_public_booking_show_view_renders_slot_picker(): void
    {
        $response = $this->get(route('online-booking.show'));

        $response->assertOk();
        $response->assertSee('booking-doctor');
        $response->assertSee('booking-date');
        $response->assertSee('slots-container');
        $response->assertSee(route('online-booking.slots'));
    }
}
