<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TreatmentBooking;
use App\Models\TreatmentService;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTreatmentsTest extends TestCase
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

    public function test_can_list_treatment_bookings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = TreatmentService::factory()->create(['tenant_id' => $tenant->id]);

        TreatmentBooking::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/treatments');

        $response->assertSuccessful()->assertJsonCount(2, 'data');
    }

    public function test_can_create_treatment_booking(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = TreatmentService::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/treatments', [
                'patient_id' => $patient->id,
                'treatment_service_id' => $service->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '11:00',
                'end_time' => '11:45',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'BOOKED')
            ->assertJsonPath('data.patient.id', $patient->id);
    }

    public function test_treatment_store_validates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/treatments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id', 'treatment_service_id', 'booking_date', 'start_time']);
    }

    public function test_can_complete_treatment_booking(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = TreatmentService::factory()->create(['tenant_id' => $tenant->id]);
        $booking = TreatmentBooking::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'status' => 'BOOKED',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/treatments/{$booking->id}/complete");

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_can_cancel_treatment_booking(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = TreatmentService::factory()->create(['tenant_id' => $tenant->id]);
        $booking = TreatmentBooking::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'status' => 'BOOKED',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/treatments/{$booking->id}/cancel", [
                'reason' => 'Patient unavailable',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'CANCELLED');
    }
}
