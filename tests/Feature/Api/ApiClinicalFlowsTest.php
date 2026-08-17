<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the consultation sub-resource endpoints (vitals, diagnoses, notes,
 * complete, amend), the patient follow-up/investigation/consent endpoints,
 * and the invoice refund endpoint — all the previously orphaned service
 * methods that had no API surface.
 */
class ApiClinicalFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function tokenHeader(User $user): array
    {
        $issued = app(TokenService::class)->create($user, 'test', ['*']);

        return ['Authorization' => 'Bearer ' . $issued['token']];
    }

    private function consultation(Tenant $tenant, User $doctor, Patient $patient): Consultation
    {
        return Consultation::create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'medicine_system' => 'AYURVEDA',
            'consultation_type' => 'OPD',
            'status' => 'DRAFT',
            'chief_complaint' => 'Chronic lower back pain',
            'diagnosis_summary' => 'Vata vyadhi — gridhrasi',
        ]);
    }

    // --- Consultation sub-resources ---

    public function test_can_record_vitals_on_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = $this->consultation($tenant, $doctor, $patient);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consultations/{$consultation->id}/vitals", [
                'systolic_bp' => '120',
                'diastolic_bp' => '80',
                'pulse' => '72',
                'height' => '170',
                'weight' => '70',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.systolic_bp', '120')
            ->assertJsonPath('data.bmi', '24.2');

        $this->assertDatabaseHas('vitals', [
            'consultation_id' => $consultation->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_can_add_diagnosis_to_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = $this->consultation($tenant, $doctor, $patient);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consultations/{$consultation->id}/diagnoses", [
                'name' => 'Gridhrasi (Sciatica)',
                'code' => 'M54.3',
                'type' => 'PRIMARY',
                'notes' => 'Radiating to left leg',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Gridhrasi (Sciatica)')
            ->assertJsonPath('data.consultation_id', $consultation->id);
    }

    public function test_can_add_clinical_note_to_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = $this->consultation($tenant, $doctor, $patient);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consultations/{$consultation->id}/notes", [
                'content' => 'Patient reports improvement after 3 sessions.',
                'note_type' => 'PROGRESS',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.note_type', 'PROGRESS')
            ->assertJsonPath('data.consultation_id', $consultation->id);
    }

    public function test_can_complete_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = $this->consultation($tenant, $doctor, $patient);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consultations/{$consultation->id}/complete");

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_can_amend_completed_consultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = $this->consultation($tenant, $doctor, $patient);
        $consultation->update(['status' => 'COMPLETED', 'completed_at' => now()]);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consultations/{$consultation->id}/amend", [
                'reason' => 'Added missed examination detail',
            ]);

        $response->assertSuccessful();
        $this->assertStringContainsString('Amendment', $response->json('data.diagnosis_summary'));
    }

    public function test_consultation_show_includes_sub_resources(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = $this->consultation($tenant, $doctor, $patient);

        $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consultations/{$consultation->id}/diagnoses", ['name' => 'Gridhrasi']);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->getJson("/api/v1/consultations/{$consultation->id}");

        $response->assertSuccessful()
            ->assertJsonStructure(['data' => ['diagnoses', 'vitals', 'notes']]);
    }

    // --- Follow-ups ---

    public function test_can_schedule_and_list_followups(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/patients/{$patient->id}/followups", [
                'due_date' => now()->addDays(7)->toDateString(),
                'instructions' => 'Review pain score and adjust treatment plan',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.patient_id', $patient->id);

        $list = $this->withHeaders($this->tokenHeader($doctor))
            ->getJson("/api/v1/patients/{$patient->id}/followups");

        $list->assertSuccessful()->assertJsonCount(1, 'data');
    }

    public function test_can_complete_followup(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $create = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/patients/{$patient->id}/followups", [
                'due_date' => now()->addDays(7)->toDateString(),
            ]);

        $followupId = $create->json('data.id');

        $response = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/followups/{$followupId}/status", [
                'status' => 'COMPLETED',
            ]);

        $response->assertSuccessful()->assertJsonPath('data.status', 'COMPLETED');
    }

    // --- Investigations ---

    public function test_can_order_and_update_investigation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $create = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/patients/{$patient->id}/investigations", [
                'name' => 'Complete Blood Count',
                'category' => 'LAB',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.status', 'REQUESTED')
            ->assertJsonPath('data.category', 'LAB');

        $investigationId = $create->json('data.id');

        $update = $this->withHeaders($this->tokenHeader($doctor))
            ->patchJson("/api/v1/investigations/{$investigationId}", [
                'status' => 'COMPLETED',
            ]);

        $update->assertSuccessful()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonStructure(['data' => ['completed_at']]);
    }

    // --- Consents ---

    public function test_can_record_and_revoke_consent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $create = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/patients/{$patient->id}/consents", [
                'consent_type' => 'TREATMENT',
                'granted' => true,
                'description' => 'Consent for panchakarma therapy',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.granted', true)
            ->assertJsonPath('data.consent_type', 'TREATMENT');

        $consentId = $create->json('data.id');

        $revoke = $this->withHeaders($this->tokenHeader($doctor))
            ->postJson("/api/v1/consents/{$consentId}/revoke");

        $revoke->assertSuccessful()->assertJsonPath('data.granted', false);
    }

    // --- Refund ---

    public function test_can_refund_payment_against_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-'.uniqid(),
            'status' => 'PAID',
            'currency' => 'INR',
            'subtotal_cents' => 100000,
            'tax_cents' => 0,
            'total_cents' => 100000,
        ]);

        $payment = Payment::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
            'payment_number' => 'PAY-'.uniqid(),
            'method' => 'CASH',
            'amount_cents' => 100000,
            'currency' => 'INR',
            'status' => 'SUCCESS',
            'collected_by' => $owner->id,
            'paid_at' => now(),
        ]);

        $response = $this->withHeaders($this->tokenHeader($owner))
            ->postJson("/api/v1/invoices/{$invoice->id}/payments/{$payment->id}/refund", [
                'amount_cents' => 40000,
                'reason' => 'Partial refund — service not fully rendered',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount_cents', 40000)
            ->assertJsonPath('data.status', 'SUCCESS');

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => 40000,
        ]);
    }
}
