<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineBookingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Appointment $appointment;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $ctx = app(TenantContext::class);
        $ctx->set($this->tenant->id);

        $this->patient = Patient::factory()->create(['phone' => '9876543210']);
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create(['name' => 'Dr. Status']);
        $this->appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'user_id' => $doctor->id,
            'status' => 'CONFIRMED',
        ]);

        $ctx->forget();
    }

    public function test_done_page_renders(): void
    {
        $this->get(route('online-booking.done'))
            ->assertOk()
            ->assertSee('Payment received')
            ->assertSee('Check booking status');
    }

    public function test_status_lookup_with_reference_and_phone(): void
    {
        $this->get(route('online-booking.status', [
            'reference' => (string) $this->appointment->id,
            'phone' => '9876543210',
        ]))
            ->assertOk()
            ->assertSee('CONFIRMED')
            ->assertSee('Dr. Status');
    }

    public function test_lookup_ignores_hash_prefix(): void
    {
        $this->get(route('online-booking.status', [
            'reference' => '#'.$this->appointment->id,
            'phone' => '+91 9876543210',
        ]))
            ->assertOk()
            ->assertSee('CONFIRMED');
    }

    public function test_wrong_phone_yields_no_match(): void
    {
        $this->get(route('online-booking.status', [
            'reference' => (string) $this->appointment->id,
            'phone' => '1111111111',
        ]))
            ->assertOk()
            ->assertSee('No booking matches');
    }

    public function test_unknown_reference_yields_no_match(): void
    {
        $this->get(route('online-booking.status', [
            'reference' => '999999',
            'phone' => '9876543210',
        ]))
            ->assertOk()
            ->assertSee('No booking matches');
    }

    public function test_lookup_is_scoped_to_booking_tenant(): void
    {
        $other = Tenant::factory()->create();
        $ctx = app(TenantContext::class);
        $ctx->set($other->id);
        $otherPatient = Patient::factory()->create(['phone' => '9876543210']);
        $otherDoctor = User::factory()->forTenant($other)->role('DOCTOR')->create();
        $otherAppointment = Appointment::factory()->create([
            'tenant_id' => $other->id,
            'patient_id' => $otherPatient->id,
            'user_id' => $otherDoctor->id,
        ]);
        $ctx->forget();

        // The public tenant resolves to the FIRST active tenant (setUp's $this->tenant),
        // so the other tenant's appointment must not be visible.
        $this->get(route('online-booking.status', [
            'reference' => (string) $otherAppointment->id,
            'phone' => '9876543210',
        ]))
            ->assertOk()
            ->assertSee('No booking matches');
    }
}
