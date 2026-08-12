<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Models\AppointmentToken;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Queue\QueueService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QueueServiceTest extends TestCase
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

    private function bookWalkIn(Tenant $tenant, User $doctor, Patient $patient, User $creator, string $time): AppointmentToken
    {
        $appt = app(AppointmentService::class)->book([
            'patient_id' => $patient->id, 'user_id' => $doctor->id, 'type' => 'WALK_IN',
            'appointment_date' => now()->format('Y-m-d'), 'start_time' => $time, 'duration_minutes' => 15,
        ], $creator);

        return $appt->token;
    }

    public function test_for_doctor_returns_tokens_ordered_by_token_number(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $p1 = $this->patient($tenant);
        $p2 = $this->patient($tenant);
        $p3 = $this->patient($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $t1 = $this->bookWalkIn($tenant, $doctor, $p1, $creator, '09:00');
        $t2 = $this->bookWalkIn($tenant, $doctor, $p2, $creator, '09:15');
        $t3 = $this->bookWalkIn($tenant, $doctor, $p3, $creator, '09:30');

        $tokens = app(QueueService::class)->forDoctor($doctor, now()->format('Y-m-d'));

        $this->assertCount(3, $tokens);
        $this->assertSame(['001', '002', '003'], $tokens->pluck('token_number')->all());
    }

    public function test_call_next_advances_oldest_waiting_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');
        $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:15');

        $called = app(QueueService::class)->callNext($doctor, now()->format('Y-m-d'), $creator);

        $this->assertNotNull($called);
        $this->assertSame('001', $called->token_number);
        $this->assertSame('CALLED', $called->status);
        $this->assertNotNull($called->called_at);
        $this->assertSame('CHECKED_IN', $called->appointment->status);
    }

    public function test_call_next_returns_null_when_queue_empty(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $this->assertNull(app(QueueService::class)->callNext($doctor, now()->format('Y-m-d'), $creator));
    }

    public function test_call_next_skips_non_waiting_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $t1 = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');
        $t2 = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:15');

        // Skip the first token (001), then callNext should return 002.
        $service = app(QueueService::class);
        $service->skip($t1, $creator);
        $called = $service->callNext($doctor, now()->format('Y-m-d'), $creator);

        $this->assertSame('002', $called->token_number);
    }

    public function test_start_consultation_transitions_called_to_in_progress(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $service = app(QueueService::class);
        $service->callNext($doctor, now()->format('Y-m-d'), $creator);
        $token = $service->startConsultation($token, $creator);

        $this->assertSame('IN_PROGRESS', $token->status);
        $this->assertSame('IN_CONSULTATION', $token->appointment->status);
    }

    public function test_start_consultation_rejects_waiting_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $this->expectException(ValidationException::class);
        app(QueueService::class)->startConsultation($token, $creator);
    }

    public function test_complete_finishes_token_and_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $service = app(QueueService::class);
        $service->callNext($doctor, now()->format('Y-m-d'), $creator);
        $service->startConsultation($token, $creator);
        $token = $service->complete($token, $creator);

        $this->assertSame('DONE', $token->status);
        $this->assertSame('COMPLETED', $token->appointment->status);
    }

    public function test_skip_marks_token_and_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $token = app(QueueService::class)->skip($token, $creator);

        $this->assertSame('SKIPPED', $token->status);
        $this->assertSame('NO_SHOW', $token->appointment->status);
    }

    public function test_complete_rejects_already_done_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $service = app(QueueService::class);
        $service->callNext($doctor, now()->format('Y-m-d'), $creator);
        $service->startConsultation($token, $creator);
        $token = $service->complete($token, $creator);

        $this->expectException(ValidationException::class);
        $service->complete($token, $creator);
    }

    public function test_recall_restores_skipped_token_to_waiting(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $service = app(QueueService::class);
        $service->skip($token, $creator);
        $token = $service->recall($token, $creator);

        $this->assertSame('WAITING', $token->status);
        $this->assertSame('SCHEDULED', $token->appointment->status);
    }

    public function test_recall_rejects_non_skipped_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $this->expectException(ValidationException::class);
        app(QueueService::class)->recall($token, $creator);
    }

    public function test_stats_counts_each_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $t1 = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');
        $t2 = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:15');
        $t3 = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:30');
        $t4 = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:45');

        $service = app(QueueService::class);
        $service->skip($t1, $creator);                              // skipped
        $service->callNext($doctor, now()->format('Y-m-d'), $creator); // 002 → called
        $service->startConsultation($t2, $creator);                  // 002 → in_progress
        $service->complete($t2, $creator);                          // 002 → done

        $stats = $service->stats($doctor, now()->format('Y-m-d'));

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['waiting']);   // 003, 004
        $this->assertSame(0, $stats['called']);
        $this->assertSame(0, $stats['in_progress']);
        $this->assertSame(1, $stats['done']);      // 002
        $this->assertSame(1, $stats['skipped']);   // 001
    }

    public function test_current_returns_active_token(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();
        $token = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, '09:00');

        $service = app(QueueService::class);
        $this->assertNull($service->current($doctor, now()->format('Y-m-d')));

        $service->callNext($doctor, now()->format('Y-m-d'), $creator);
        $current = $service->current($doctor, now()->format('Y-m-d'));

        $this->assertNotNull($current);
        $this->assertSame($token->id, $current->id);
        $this->assertSame('CALLED', $current->status);
    }

    public function test_queue_is_isolated_per_doctor(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctorA = $this->doctor($tenant);
        $doctorB = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $tA = $this->bookWalkIn($tenant, $doctorA, $this->patient($tenant), $creator, '09:00');
        $tB = $this->bookWalkIn($tenant, $doctorB, $this->patient($tenant), $creator, '09:00');

        // Both doctors get token "001" — sequences are independent per doctor.
        $this->assertSame('001', $tA->token_number);
        $this->assertSame('001', $tB->token_number);

        $service = app(QueueService::class);
        $calledA = $service->callNext($doctorA, now()->format('Y-m-d'), $creator);
        $calledB = $service->callNext($doctorB, now()->format('Y-m-d'), $creator);

        // Calling A's next must not affect B's queue.
        $this->assertSame('001', $calledA->token_number);
        $this->assertSame('001', $calledB->token_number);
        $this->assertSame($tB->id, $calledB->id);
    }

    public function test_queue_is_tenant_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $doctorA = $this->doctor($tenantA);
        $creatorA = User::factory()->forTenant($tenantA)->role('RECEPTIONIST')->create();
        $this->bookWalkIn($tenantA, $doctorA, $this->patient($tenantA), $creatorA, '09:00');

        $this->setTenant($tenantB);
        $doctorB = $this->doctor($tenantB);

        // Tenant B's doctor queue must be empty.
        $tokens = app(QueueService::class)->forDoctor($doctorB, now()->format('Y-m-d'));
        $this->assertCount(0, $tokens);
    }

    public function test_full_queue_lifecycle(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);
        $creator = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $tokens[] = $this->bookWalkIn($tenant, $doctor, $this->patient($tenant), $creator, sprintf('09:%02d', $i * 15));
        }

        $service = app(QueueService::class);
        $date = now()->format('Y-m-d');

        // Process tokens in FIFO order: call → start → complete for each.
        foreach ($tokens as $token) {
            $called = $service->callNext($doctor, $date, $creator);
            $this->assertNotNull($called);
            $service->startConsultation($called, $creator);
            $service->complete($called, $creator);
        }

        $stats = $service->stats($doctor, $date);
        $this->assertSame(3, $stats['done']);
        $this->assertSame(0, $stats['waiting']);
    }
}
