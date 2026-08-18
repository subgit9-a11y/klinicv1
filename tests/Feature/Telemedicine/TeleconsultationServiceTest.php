<?php

declare(strict_types=1);

namespace Tests\Feature\Telemedicine;

use App\Contracts\VideoProviderInterface;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Telemedicine\TeleconsultationService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeleconsultationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_schedule_creates_scheduled_teleconsultation_without_meeting_when_provider_unconfigured(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $tc = app(TeleconsultationService::class)->schedule([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'scheduled_at' => now()->addHour()->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $this->assertSame('SCHEDULED', $tc->status);
        $this->assertNull($tc->meeting_url);
        $this->assertNull($tc->meeting_id);
    }

    public function test_schedule_provisions_real_meeting_url_when_provider_configured(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $this->app->bind(VideoProviderInterface::class, function () {
            return new class implements VideoProviderInterface {
                public function isConfigured(): bool { return true; }
                public function name(): string { return 'FakeVideo'; }
                public function createMeeting(string $title, \DateTimeInterface $start, \DateTimeInterface $end, ?string $tenantId = null): array
                {
                    return ['success' => true, 'meeting_id' => 'mtg-123', 'meeting_url' => 'https://meet.example.com/mtg-123', 'message' => 'ok'];
                }
                public function deleteMeeting(string $meetingId): array
                {
                    return ['success' => true, 'message' => 'deleted'];
                }
            };
        });

        $tc = app(TeleconsultationService::class)->schedule([
            'patient_id' => $patient->id,
            'title' => 'Follow-up',
        ]);

        $this->assertSame('https://meet.example.com/mtg-123', $tc->meeting_url);
        $this->assertSame('mtg-123', $tc->meeting_id);
    }

    public function test_start_then_end_lifecycle_transitions(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(TeleconsultationService::class);
        $tc = $service->schedule(['patient_id' => $patient->id]);

        $started = $service->start($tc);
        $this->assertSame('STARTED', $started->status);
        $this->assertNotNull($started->started_at);

        $ended = $service->end($started);
        $this->assertSame('COMPLETED', $ended->status);
        $this->assertNotNull($ended->ended_at);
    }

    public function test_start_rejects_completed_teleconsultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(TeleconsultationService::class);
        $tc = $service->schedule(['patient_id' => $patient->id]);
        $service->start($tc);
        $service->end($tc);

        $this->expectException(\DomainException::class);
        $service->start($tc->refresh());
    }

    public function test_cancel_releases_upstream_meeting_and_sets_cancelled(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $deleted = [];
        $this->app->bind(VideoProviderInterface::class, function () use (&$deleted) {
            return new class($deleted) implements VideoProviderInterface {
                public function __construct(private array &$deleted) {}
                public function isConfigured(): bool { return true; }
                public function name(): string { return 'FakeVideo'; }
                public function createMeeting(string $title, \DateTimeInterface $start, \DateTimeInterface $end, ?string $tenantId = null): array
                {
                    return ['success' => true, 'meeting_id' => 'mtg-999', 'meeting_url' => 'https://meet.example.com/mtg-999', 'message' => 'ok'];
                }
                public function deleteMeeting(string $meetingId): array
                {
                    $this->deleted[] = $meetingId;

                    return ['success' => true, 'message' => 'deleted'];
                }
            };
        });

        $service = app(TeleconsultationService::class);
        $tc = $service->schedule(['patient_id' => $patient->id]);
        $this->assertNotNull($tc->meeting_id);

        $cancelled = $service->cancel($tc);
        $this->assertSame('CANCELLED', $cancelled->status);

        $this->assertContains('mtg-999', $deleted);
    }

    public function test_cancel_rejects_already_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(TeleconsultationService::class);
        $tc = $service->schedule(['patient_id' => $patient->id]);
        $service->start($tc);
        $service->end($tc);

        $this->expectException(\DomainException::class);
        $service->cancel($tc->refresh());
    }

    public function test_mark_no_show_transitions_scheduled_to_no_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(TeleconsultationService::class);
        $tc = $service->schedule(['patient_id' => $patient->id]);
        $noShow = $service->markNoShow($tc);

        $this->assertSame('NO_SHOW', $noShow->status);
    }

    public function test_cross_tenant_start_is_rejected(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $patientA = Patient::factory()->create(['tenant_id' => $tenantA->id]);

        $service = app(TeleconsultationService::class);
        $tc = $service->schedule(['patient_id' => $patientA->id]);

        $this->setTenant($tenantB);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->start($tc);
    }
}
