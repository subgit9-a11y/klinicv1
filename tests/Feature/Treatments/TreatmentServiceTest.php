<?php

declare(strict_types=1);

namespace Tests\Feature\Treatments;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\Therapist;
use App\Models\TreatmentBooking;
use App\Models\TreatmentPlan;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Models\User;
use App\Policies\TreatmentPolicy;
use App\Services\Tenancy\TenantContext;
use App\Services\Treatments\BookingCollisionException;
use App\Services\Treatments\TreatmentBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function clinicStaff(Tenant $tenant, string $role = 'CLINIC_OWNER'): User
    {
        return User::factory()->forTenant($tenant)->role($role)->create();
    }

    public function test_book_creates_booking_with_computed_end_time(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $service = TreatmentService::factory()->create(['duration_minutes' => 45]);
        $therapist = Therapist::factory()->create();
        $room = TreatmentRoom::factory()->create();

        $booking = app(TreatmentBookingService::class)->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'treatment_room_id' => $room->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
        ]);

        $this->assertSame('BOOKED', $booking->status);
        $this->assertSame('10:45:00', $booking->end_time);
        $this->assertSame('PAY_AT_CLINIC', $booking->payment_mode);
    }

    public function test_book_uses_explicit_end_time_when_provided(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $service = TreatmentService::factory()->create();
        $therapist = Therapist::factory()->create();

        $booking = app(TreatmentBookingService::class)->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'treatment_room_id' => null,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $this->assertSame('11:00:00', $booking->end_time);
    }

    public function test_book_throws_on_therapist_collision(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $service = TreatmentService::factory()->create();
        $therapist = Therapist::factory()->create();
        $room = TreatmentRoom::factory()->create();

        $svc = app(TreatmentBookingService::class);

        $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'treatment_room_id' => $room->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->expectException(BookingCollisionException::class);
        $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'treatment_room_id' => $room->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:15:00',
            'end_time' => '10:45:00',
        ]);
    }

    public function test_book_allows_non_overlapping_therapist_slots(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $service = TreatmentService::factory()->create();
        $therapist = Therapist::factory()->create();

        $svc = app(TreatmentBookingService::class);

        $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $booking2 = $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
        ]);

        $this->assertSame('BOOKED', $booking2->status);
    }

    public function test_book_throws_on_room_collision(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $service = TreatmentService::factory()->create();
        $therapist1 = Therapist::factory()->create();
        $therapist2 = Therapist::factory()->create();
        $room = TreatmentRoom::factory()->create();

        $svc = app(TreatmentBookingService::class);

        $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist1->id,
            'treatment_room_id' => $room->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->expectException(BookingCollisionException::class);
        $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist2->id,
            'treatment_room_id' => $room->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:15:00',
            'end_time' => '10:45:00',
        ]);
    }

    public function test_book_ignores_cancelled_bookings_for_collision(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $service = TreatmentService::factory()->create();
        $therapist = Therapist::factory()->create();

        $svc = app(TreatmentBookingService::class);

        $booking = $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $svc->cancel($booking);

        $newBooking = $svc->book([
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->assertSame('BOOKED', $newBooking->status);
    }

    public function test_complete_sets_status_and_timestamp(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $booking = TreatmentBooking::factory()->create(['status' => 'BOOKED']);

        app(TreatmentBookingService::class)->complete($booking);

        $booking->refresh();
        $this->assertSame('COMPLETED', $booking->status);
        $this->assertNotNull($booking->completed_at);
    }

    public function test_complete_increments_treatment_plan_progress(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $plan = TreatmentPlan::factory()->create([
            'total_sessions' => 3,
            'completed_sessions' => 2,
            'status' => 'ACTIVE',
        ]);
        $booking = TreatmentBooking::factory()->create([
            'treatment_plan_id' => $plan->id,
            'status' => 'BOOKED',
        ]);

        app(TreatmentBookingService::class)->complete($booking);

        $plan->refresh();
        $this->assertSame(3, $plan->completed_sessions);
        $this->assertSame('COMPLETED', $plan->status);
        $this->assertNotNull($plan->ends_at);
    }

    public function test_cancel_sets_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $booking = TreatmentBooking::factory()->create(['status' => 'BOOKED']);

        app(TreatmentBookingService::class)->cancel($booking);

        $this->assertSame('CANCELLED', $booking->fresh()->status);
    }

    public function test_for_date_returns_bookings_ordered_by_time(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $date = Carbon::parse('2026-01-15');

        TreatmentBooking::factory()->create([
            'booking_date' => $date->toDateString(),
            'start_time' => '11:00:00',
        ]);
        TreatmentBooking::factory()->create([
            'booking_date' => $date->toDateString(),
            'start_time' => '09:00:00',
        ]);

        $result = app(TreatmentBookingService::class)->forDate($date);

        $this->assertCount(2, $result);
        $this->assertSame('09:00:00', $result->first()->start_time);
    }

    public function test_has_therapist_collision_helper(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $therapist = Therapist::factory()->create();

        TreatmentBooking::factory()->create([
            'therapist_id' => $therapist->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $svc = app(TreatmentBookingService::class);

        $this->assertTrue($svc->hasTherapistCollision($therapist->id, '2026-01-15', '10:15:00', '10:45:00'));
        $this->assertFalse($svc->hasTherapistCollision($therapist->id, '2026-01-15', '10:30:00', '11:00:00'));
        $this->assertFalse($svc->hasTherapistCollision($therapist->id, '2026-01-16', '10:00:00', '10:30:00'));
    }

    public function test_has_room_collision_helper(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $room = TreatmentRoom::factory()->create();

        TreatmentBooking::factory()->create([
            'treatment_room_id' => $room->id,
            'booking_date' => '2026-01-15',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $svc = app(TreatmentBookingService::class);

        $this->assertTrue($svc->hasRoomCollision($room->id, '2026-01-15', '10:15:00', '10:45:00'));
        $this->assertFalse($svc->hasRoomCollision($room->id, '2026-01-15', '10:30:00', '11:00:00'));
    }

    public function test_policy_allows_clinic_owner_to_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = $this->clinicStaff($tenant);

        $this->assertTrue((new TreatmentPolicy)->create($user));
    }

    public function test_policy_blocks_cross_tenant_view(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $booking = TreatmentBooking::factory()->create();

        $this->setTenant($tenantB);
        $userB = $this->clinicStaff($tenantB);

        $this->assertFalse((new TreatmentPolicy)->view($userB, $booking));
    }

    public function test_policy_allows_super_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $booking = TreatmentBooking::factory()->create();
        $superAdmin = User::factory()->role('SUPER_ADMIN')->create();

        $this->assertTrue((new TreatmentPolicy)->view($superAdmin, $booking));
        $this->assertTrue((new TreatmentPolicy)->update($superAdmin, $booking));
    }
}
