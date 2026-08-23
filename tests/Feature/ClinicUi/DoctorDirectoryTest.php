<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\Staff\DoctorDirectory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DoctorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_owner_can_view_doctors(): void
    {
        User::factory()->forTenant($this->tenant)->role('DOCTOR')->create(['name' => 'Dr. Visible']);

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->assertStatus(200)
            ->assertSee('Dr. Visible');
    }

    public function test_non_permitted_user_gets_403(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        Livewire::actingAs($receptionist)
            ->test(DoctorDirectory::class)
            ->assertStatus(403);
    }

    public function test_directory_never_shows_other_tenant_doctors(): void
    {
        $other = Tenant::factory()->create();
        User::factory()->forTenant($other)->role('DOCTOR')->create(['name' => 'Dr. CrossTenant']);

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->assertDontSee('Dr. CrossTenant');
    }

    public function test_doctors_api_listing_is_tenant_scoped(): void
    {
        $other = Tenant::factory()->create();
        User::factory()->forTenant($other)->role('DOCTOR')->create(['name' => 'Dr. Leak']);
        User::factory()->forTenant($this->tenant)->role('DOCTOR')->create(['name' => 'Dr. Local']);

        $headers = ['Authorization' => 'Bearer '.app(TokenService::class)->create($this->owner, 'docs', ['*'])['token']];

        $this->withHeaders($headers)->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertDontSee('Dr. Leak');
    }

    public function test_onboard_creates_doctor(): void
    {
        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->set('name', 'Dr. New')
            ->set('email', 'new@clinic.test')
            ->set('password', 'secret123')
            ->set('specialization', 'Dravyaguna')
            ->set('consultation_fee_rupees', 500)
            ->call('onboard');

        $this->assertDatabaseHas('users', [
            'email' => 'new@clinic.test',
            'tenant_id' => $this->tenant->id,
            'role' => 'DOCTOR',
            'consultation_fee_cents' => 50000,
        ]);
    }

    public function test_save_profile_sets_duration_and_capacity(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->call('select', $doctor->id)
            ->set('edit_consultation_duration_minutes', 45)
            ->set('edit_max_daily_appointments', 20)
            ->call('saveProfile');

        $fresh = $doctor->fresh();
        $this->assertSame(45, $fresh->consultation_duration_minutes);
        $this->assertSame(20, $fresh->max_daily_appointments);
    }

    public function test_save_profile_rejects_invalid_duration(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->call('select', $doctor->id)
            ->set('edit_consultation_duration_minutes', 2)
            ->call('saveProfile')
            ->assertHasErrors(['edit_consultation_duration_minutes']);

        $this->assertNull($doctor->fresh()->consultation_duration_minutes);
    }

    public function test_save_schedule_persists_availability_with_break(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->call('select', $doctor->id, 'schedule')
            ->set('schedule.MON.enabled', true)
            ->set('schedule.MON.start', '09:00')
            ->set('schedule.MON.end', '13:00')
            ->set('schedule.MON.break_start', '11:00')
            ->set('schedule.MON.break_end', '11:30')
            ->call('saveSchedule');

        $this->assertDatabaseHas('doctor_availability', [
            'user_id' => $doctor->id,
            'day_of_week' => 'MON',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'break_start_time' => '11:00',
            'break_end_time' => '11:30',
        ]);
    }

    public function test_add_leave_records_doctor_leave(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->call('select', $doctor->id, 'leave')
            ->set('leave_start', '2026-09-05')
            ->set('leave_end', '2026-09-07')
            ->set('leave_type', 'LEAVE')
            ->call('addLeave');

        $this->assertDatabaseHas('doctor_leaves', [
            'user_id' => $doctor->id,
            'type' => 'LEAVE',
        ]);
    }

    public function test_toggle_active_flips_doctor(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create(['is_active' => true]);

        Livewire::actingAs($this->owner)
            ->test(DoctorDirectory::class)
            ->call('toggleActive', $doctor->id);

        $this->assertFalse($doctor->fresh()->is_active);
    }
}
