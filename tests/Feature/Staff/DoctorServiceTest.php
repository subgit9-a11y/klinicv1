<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\DoctorAvailability;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Staff\DoctorService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorServiceTest extends TestCase
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

    public function test_onboard_creates_doctor_with_profile_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $doctor = app(DoctorService::class)->onboard([
            'name' => 'Dr. Arya',
            'email' => 'arya@clinic.test',
            'password' => 'secret123',
            'specialization' => 'Panchakarma',
            'registration_number' => 'AYU-2024-001',
            'medicine_system' => 'AYURVEDA',
            'consultation_fee_cents' => 50000,
            'followup_fee_cents' => 20000,
        ]);

        $this->assertSame('DOCTOR', $doctor->role);
        $this->assertSame($tenant->id, $doctor->tenant_id);
        $this->assertSame('Panchakarma', $doctor->specialization);
        $this->assertSame(50000, $doctor->consultation_fee_cents);
    }

    public function test_onboard_validates_unique_email(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        User::factory()->forTenant($tenant)->create(['email' => 'dup@clinic.test']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DoctorService::class)->onboard([
            'name' => 'Dup',
            'email' => 'dup@clinic.test',
            'password' => 'secret123',
        ]);
    }

    public function test_set_availability_replaces_existing_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        DoctorAvailability::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'day_of_week' => 'MON',
        ]);

        $slots = app(DoctorService::class)->setAvailability($doctor, [
            ['day_of_week' => 'MON', 'start_time' => '09:00', 'end_time' => '13:00'],
            ['day_of_week' => 'WED', 'start_time' => '10:00', 'end_time' => '14:00'],
        ]);

        $this->assertCount(2, $slots);
        $this->assertDatabaseCount('doctor_availability', 2);
        $this->assertDatabaseHas('doctor_availability', [
            'user_id' => $doctor->id,
            'day_of_week' => 'WED',
        ]);
    }

    public function test_set_availability_rejects_invalid_time_range(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DoctorService::class)->setAvailability($doctor, [
            ['day_of_week' => 'MON', 'start_time' => '14:00', 'end_time' => '09:00'],
        ]);
    }

    public function test_set_availability_persists_break_window(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        app(DoctorService::class)->setAvailability($doctor, [
            [
                'day_of_week' => 'MON',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_start_time' => '13:00',
                'break_end_time' => '14:00',
            ],
        ]);

        $this->assertDatabaseHas('doctor_availability', [
            'user_id' => $doctor->id,
            'day_of_week' => 'MON',
            'break_start_time' => '13:00',
            'break_end_time' => '14:00',
        ]);
    }

    public function test_set_availability_rejects_break_end_before_break_start(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DoctorService::class)->setAvailability($doctor, [
            [
                'day_of_week' => 'MON',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'break_start_time' => '14:00',
                'break_end_time' => '13:00',
            ],
        ]);
    }

    public function test_set_leave_creates_approved_leave_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $leave = app(DoctorService::class)->setLeave($doctor, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'reason' => 'Conference',
            'type' => 'LEAVE',
        ]);

        $leave->refresh();
        $this->assertSame($doctor->id, $leave->user_id);
        $this->assertSame($tenant->id, $leave->tenant_id);
        $this->assertSame('2026-09-01', $leave->start_date->toDateString());
        $this->assertSame('2026-09-03', $leave->end_date->toDateString());
        $this->assertSame('Conference', $leave->reason);
        $this->assertTrue($leave->is_approved);
    }

    public function test_set_leave_rejects_end_before_start(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DoctorService::class)->setLeave($doctor, [
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-01',
        ]);
    }

    public function test_update_profile_updates_doctor_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $updated = app(DoctorService::class)->updateProfile($doctor, [
            'specialization' => 'Internal Medicine',
            'consultation_fee_cents' => 75000,
        ]);

        $this->assertSame('Internal Medicine', $updated->specialization);
        $this->assertSame(75000, $updated->consultation_fee_cents);
    }

    public function test_cross_tenant_update_rejected(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $doctorA = User::factory()->forTenant($tenantA)->create(['role' => 'DOCTOR']);

        $this->setTenant($tenantB);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DoctorService::class)->updateProfile($doctorA, ['specialization' => 'X']);
    }

    public function test_api_clinic_owner_can_onboard_doctor(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/doctors', [
                'name' => 'Dr. New',
                'email' => 'new@clinic.test',
                'password' => 'secret123',
                'specialization' => 'Cardiology',
                'medicine_system' => 'AYURVEDA',
                'consultation_fee_cents' => 60000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('role', 'DOCTOR')
            ->assertJsonPath('specialization', 'Cardiology')
            ->assertJsonPath('consultation_fee', 600);
    }

    public function test_api_doctor_cannot_onboard_other_doctors(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/doctors', [
                'name' => 'Dr. X',
                'email' => 'x@clinic.test',
                'password' => 'secret123',
            ])
            ->assertStatus(403);
    }

    public function test_api_clinic_owner_can_set_availability(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($owner))
            ->putJson("/api/v1/doctors/{$doctor->id}/availability", [
                'slots' => [
                    ['day_of_week' => 'MON', 'start_time' => '09:00', 'end_time' => '17:00'],
                    ['day_of_week' => 'FRI', 'start_time' => '10:00', 'end_time' => '15:00'],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonCount(2);
    }
}
