<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResourcesTest extends TestCase
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

    public function test_can_list_appointments_filtered_by_date(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2025-06-15',
        ]);
        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'appointment_date' => '2025-06-16',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/appointments?date=2025-06-15');

        $response->assertSuccessful()->assertJsonCount(1, 'data');
    }

    public function test_can_create_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/appointments', [
                'patient_id' => $patient->id,
                'type' => 'IN_PERSON',
                'appointment_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'duration_minutes' => 30,
                'reason' => 'Fever',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'IN_PERSON')
            ->assertJsonPath('data.status', 'SCHEDULED');
    }

    public function test_appointment_store_validates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/appointments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id', 'type', 'appointment_date', 'start_time']);
    }

    public function test_can_cancel_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'SCHEDULED',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/appointments/{$appointment->id}/cancel", [
                'reason' => 'Patient request',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_can_create_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/consultations', [
                'patient_id' => $patient->id,
                'medicine_system' => 'AYURVEDA',
                'chief_complaint' => 'Joint pain',
                'assessment' => 'Vata imbalance',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.medicine_system', 'AYURVEDA')
            ->assertJsonPath('data.patient.id', $patient->id);
    }

    public function test_can_list_consultations_by_system(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Consultation::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'medicine_system' => 'AYURVEDA',
        ]);
        Consultation::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'medicine_system' => 'HOMEOPATHY',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/consultations?system=AYURVEDA');

        $response->assertSuccessful()->assertJsonCount(1, 'data');
    }

    public function test_can_create_invoice_with_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/invoices', [
                'patient_id' => $patient->id,
                'source' => 'OPD',
                'items' => [
                    [
                        'description' => 'Consultation fee',
                        'quantity' => 1,
                        'unit_price_cents' => 50000,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.patient.id', $patient->id);

        $this->assertDatabaseHas('invoice_items', ['description' => 'Consultation fee']);
    }

    public function test_can_issue_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'DRAFT',
            'subtotal_cents' => 10000,
            'total_cents' => 10000,
            'amount_due_cents' => 10000,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/invoices/{$invoice->id}/issue");

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'ISSUED');
    }

    public function test_can_record_payment_on_issued_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'ISSUED',
            'subtotal_cents' => 10000,
            'total_cents' => 10000,
            'amount_due_cents' => 10000,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'method' => 'CASH',
                'amount_cents' => 10000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.method', 'CASH')
            ->assertJsonPath('data.amount_cents', 10000);
    }

    public function test_payment_rejected_on_draft_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'method' => 'CASH',
                'amount_cents' => 5000,
            ]);

        $response->assertStatus(500);
    }
}
