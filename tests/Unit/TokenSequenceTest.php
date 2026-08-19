<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\AppointmentToken;
use App\Models\Tenant;
use App\Models\TokenSequence;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the dedicated per-doctor per-day token counter: numbers are issued
 * sequentially from a locked counter row (not MAX over existing tokens), and
 * a counter created mid-day continues after already-issued tokens.
 */
class TokenSequenceTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();

        return [$tenant, $doctor];
    }

    public function test_counter_increments_sequentially(): void
    {
        [$tenant, $doctor] = $this->tenantUser();
        $date = now()->toDateString();

        DB::transaction(function () use ($tenant, $doctor, $date) {
            $this->assertSame('001', TokenSequence::lockFor($tenant->id, $doctor->id, $date)->consume());
            $this->assertSame('002', TokenSequence::lockFor($tenant->id, $doctor->id, $date)->consume());
        });

        // Same counter row is reused — exactly one row per doctor/day.
        $this->assertSame(1, TokenSequence::count());
        $this->assertSame(3, TokenSequence::first()->next_number);
    }

    public function test_counter_initializes_after_existing_tokens(): void
    {
        [$tenant, $doctor] = $this->tenantUser();
        $date = now()->toDateString();

        // Simulate pre-counter tokens (deployment mid-day).
        $patient = \App\Models\Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appt = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
        ]);
        AppointmentToken::create([
            'tenant_id' => $tenant->id,
            'appointment_id' => $appt->id,
            'user_id' => $doctor->id,
            'token_number' => '007',
            'appointment_date' => $date,
            'status' => 'DONE',
        ]);

        DB::transaction(function () use ($tenant, $doctor, $date) {
            $this->assertSame('008', TokenSequence::lockFor($tenant->id, $doctor->id, $date)->consume());
        });
    }

    public function test_counters_are_independent_per_doctor_and_day(): void
    {
        [$tenant, $doctor] = $this->tenantUser();
        $other = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        DB::transaction(function () use ($tenant, $doctor, $other, $today, $tomorrow) {
            $this->assertSame('001', TokenSequence::lockFor($tenant->id, $doctor->id, $today)->consume());
            $this->assertSame('001', TokenSequence::lockFor($tenant->id, $other->id, $today)->consume());
            $this->assertSame('001', TokenSequence::lockFor($tenant->id, $doctor->id, $tomorrow)->consume());
            $this->assertSame('002', TokenSequence::lockFor($tenant->id, $doctor->id, $today)->consume());
        });

        $this->assertSame(3, TokenSequence::count());
    }

    public function test_walk_in_bookings_get_sequential_tokens_via_service(): void
    {
        [$tenant, $doctor] = $this->tenantUser();
        $patient = \App\Models\Patient::factory()->create(['tenant_id' => $tenant->id]);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $service = app(AppointmentService::class);

        $book = fn (string $time) => $service->book([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'WALK_IN',
            'appointment_date' => now()->toDateString(),
            'start_time' => $time,
            'end_time' => $time,
            'duration_minutes' => 30,
        ], $creator);

        $a = $book('09:00');
        $b = $book('09:30');

        $this->assertSame('001', $a->token->token_number);
        $this->assertSame('002', $b->token->token_number);
    }
}
