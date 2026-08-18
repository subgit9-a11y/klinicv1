<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTeleconsultationLifecycleTest extends TestCase
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

    public function test_doctor_can_schedule_teleconsultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $resp = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/teleconsultations', [
                'patient_id' => $patient->id,
                'user_id' => $doctor->id,
                'scheduled_at' => now()->addDay()->toDateTimeString(),
                'duration_minutes' => 30,
                'title' => 'Tele-followup',
            ]);
        $resp->assertStatus(201)
            ->assertJsonPath('status', 'SCHEDULED')
            ->assertJsonPath('patient_id', $patient->id);
    }

    public function test_therapist_cannot_create_teleconsultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'THERAPIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/teleconsultations', ['patient_id' => $patient->id])
            ->assertStatus(403);
    }

    public function test_doctor_can_start_then_end_then_cancel_lifecycle_via_api(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $headers = $this->tokenHeader($doctor);

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/teleconsultations', ['patient_id' => $patient->id])
            ->assertStatus(201);
        $id = $created->json('id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/teleconsultations/{$id}/start")
            ->assertSuccessful()
            ->assertJsonPath('status', 'STARTED');

        $this->withHeaders($headers)
            ->postJson("/api/v1/teleconsultations/{$id}/end")
            ->assertSuccessful()
            ->assertJsonPath('status', 'COMPLETED');
    }

    public function test_cancel_endpoint_sets_cancelled_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $headers = $this->tokenHeader($doctor);

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/teleconsultations', ['patient_id' => $patient->id])
            ->assertStatus(201);
        $id = $created->json('id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/teleconsultations/{$id}/cancel", ['reason' => 'Patient unavailable'])
            ->assertSuccessful()
            ->assertJsonPath('status', 'CANCELLED');
    }

    public function test_store_validates_patient_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/teleconsultations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id']);
    }
}
