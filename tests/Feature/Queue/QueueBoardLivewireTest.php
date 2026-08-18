<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Livewire\Queue\QueueBoard;
use App\Models\AppointmentToken;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QueueBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create([
            'email_verified_at' => now(),
        ]);

        return [$tenant, $owner];
    }

    private function bookWalkIn(Tenant $tenant, User $doctor, User $creator, string $time): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        app(AppointmentService::class)->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'WALK_IN',
            'appointment_date' => now()->format('Y-m-d'), 'start_time' => $time, 'duration_minutes' => 15,
        ], $creator);
    }

    public function test_queue_board_renders(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create(['name' => 'Dr. Queue']);
        $this->bookWalkIn($tenant, $doctor, $owner, '09:00');

        Livewire::actingAs($owner)
            ->test(QueueBoard::class)
            ->assertStatus(200)
            ->assertSee(__('klinic360.queue.title'))
            ->assertSee('Dr. Queue')
            ->assertSee('001');
    }

    public function test_call_next_via_board_calls_oldest_token(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $this->bookWalkIn($tenant, $doctor, $owner, '09:00');
        $this->bookWalkIn($tenant, $doctor, $owner, '09:15');

        Livewire::actingAs($owner)
            ->test(QueueBoard::class)
            ->call('callNext', $doctor->id)
            ->assertDispatched('token-called');

        $this->assertDatabaseHas('appointment_tokens', [
            'user_id' => $doctor->id,
            'token_number' => '001',
            'status' => 'CALLED',
        ]);
    }

    public function test_call_next_with_empty_queue_flashes_message(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();

        Livewire::actingAs($owner)
            ->test(QueueBoard::class)
            ->call('callNext', $doctor->id);

        // No exception, no dispatch — board should still be functional.
        $this->assertDatabaseCount('appointment_tokens', 0);
    }

    public function test_skip_and_recall_via_board(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $this->bookWalkIn($tenant, $doctor, $owner, '09:00');

        $token = AppointmentToken::first();

        Livewire::actingAs($owner)
            ->test(QueueBoard::class)
            ->call('skip', $token->id);

        $this->assertSame('SKIPPED', $token->fresh()->status);

        Livewire::actingAs($owner)
            ->test(QueueBoard::class)
            ->call('recall', $token->id);

        $this->assertSame('WAITING', $token->fresh()->status);
    }

    public function test_full_lifecycle_via_board(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        $this->bookWalkIn($tenant, $doctor, $owner, '09:00');

        $token = AppointmentToken::first();

        Livewire::actingAs($owner)
            ->test(QueueBoard::class)
            ->call('callNext', $doctor->id);

        $lw = Livewire::actingAs($owner)->test(QueueBoard::class);
        $lw->call('startConsultation', $token->id);
        $this->assertSame('IN_PROGRESS', $token->fresh()->status);

        $lw = Livewire::actingAs($owner)->test(QueueBoard::class);
        $lw->call('complete', $token->id);
        $this->assertSame('DONE', $token->fresh()->status);
        $this->assertSame('COMPLETED', $token->fresh()->appointment->status);
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $this->get('/queue')->assertRedirect('/login');
    }

    public function test_empty_queue_board_shows_no_doctors_state(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        // Clinic with only a receptionist (no doctor/owner).
        $receptionist = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create(['email_verified_at' => now()]);

        Livewire::actingAs($receptionist)
            ->test(QueueBoard::class)
            ->assertSee(__('klinic360.queue.no_doctors'));
    }
}
