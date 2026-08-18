<?php

declare(strict_types=1);

namespace Tests\Feature\EMR;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EMR\ConsultationService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConsultationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create(['email_verified_at' => now()]);
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create(['email_verified_at' => now()]);

        return [$tenant, $owner, $doctor];
    }

    public function test_start_creates_draft_consultation(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $consultation = app(ConsultationService::class)->start([
            'patient_id' => $patient->id,
            'medicine_system' => 'AYURVEDA',
            'consultation_type' => 'OPD',
        ], $doctor);

        $this->assertSame('DRAFT', $consultation->status);
        $this->assertSame('AYURVEDA', $consultation->medicine_system);
        $this->assertSame($doctor->id, $consultation->user_id);
        $this->assertSame($patient->id, $consultation->patient_id);
        $this->assertNull($consultation->completed_at);
    }

    public function test_start_rejects_patient_from_other_tenant(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $otherTenant = Tenant::factory()->create();
        // Create the patient under the other tenant by switching context.
        app(TenantContext::class)->set($otherTenant->id);
        $patient = Patient::factory()->create(['tenant_id' => $otherTenant->id]);
        // Switch back to the first tenant and try to start a consultation.
        app(TenantContext::class)->set($tenant->id);

        $this->expectException(ModelNotFoundException::class);
        app(ConsultationService::class)->start(['patient_id' => $patient->id], $doctor);
    }

    public function test_update_saves_soap_fields(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = $service->start(['patient_id' => $patient->id], $doctor);

        $updated = $service->update($consultation, [
            'chief_complaint' => 'Vata-type headache',
            'history' => '3 days',
            'examination' => 'Tenderness on temples',
            'assessment' => 'Vata vitiation',
            'diagnosis_summary' => 'Ardhavabhedaka',
            'treatment_plan' => 'Dashamooladi taila',
            'advice' => 'Avoid cold',
            'follow_up_days' => 7,
        ], $doctor);

        $this->assertSame('Vata-type headache', $updated->chief_complaint);
        $this->assertSame('Ardhavabhedaka', $updated->diagnosis_summary);
        $this->assertSame(7, $updated->follow_up_days);
    }

    public function test_record_vitals_auto_computes_bmi(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $vital = app(ConsultationService::class)->recordVitals([
            'patient_id' => $patient->id,
            'systolic_bp' => '120',
            'diastolic_bp' => '80',
            'pulse' => '72',
            'height' => '170',  // cm
            'weight' => '70',   // kg
        ], $owner);

        $this->assertSame('120', $vital->systolic_bp);
        $this->assertSame('24.2', $vital->bmi); // 70 / (1.70^2) = 24.22
        $this->assertSame($owner->id, $vital->recorded_by);
    }

    public function test_record_vitals_without_height_weight_leaves_bmi_null(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $vital = app(ConsultationService::class)->recordVitals([
            'patient_id' => $patient->id,
            'systolic_bp' => '118',
            'diastolic_bp' => '76',
        ], $owner);

        $this->assertNull($vital->bmi);
    }

    public function test_add_diagnosis_uses_consultation_system(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = $service->start(['patient_id' => $patient->id, 'medicine_system' => 'AYURVEDA'], $doctor);

        $dx = $service->addDiagnosis($consultation, ['name' => 'Vataja Shotha', 'type' => 'PRIMARY'], $doctor);

        $this->assertSame('AYURVEDA', $dx->system);
        $this->assertSame('Vataja Shotha', $dx->name);
        $this->assertSame($consultation->id, $dx->consultation_id);
    }

    public function test_add_diagnosis_for_general_leaves_system_null(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = $service->start(['patient_id' => $patient->id], $doctor);

        $dx = $service->addDiagnosis($consultation, ['name' => 'Migraine', 'code' => 'G43.909'], $doctor);

        $this->assertNull($dx->system);
        $this->assertSame('G43.909', $dx->code);
    }

    public function test_add_note_creates_progress_note(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $note = app(ConsultationService::class)->addNote($patient->id, 'Patient improving', $owner);

        $this->assertSame('PROGRESS', $note->note_type);
        $this->assertSame('Patient improving', $note->content);
    }

    public function test_add_note_rejects_invalid_type(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);
        app(ConsultationService::class)->addNote($patient->id, 'x', $owner, 'INVALID');
    }

    public function test_complete_requires_content_or_diagnosis(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = $service->start(['patient_id' => $patient->id], $doctor);

        $this->expectException(ValidationException::class);
        $service->complete($consultation, $doctor);
    }

    public function test_complete_succeeds_with_chief_complaint(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = $service->start(['patient_id' => $patient->id], $doctor);
        $service->update($consultation, ['chief_complaint' => 'Headache'], $doctor);

        $completed = $service->complete($consultation, $doctor);

        $this->assertSame('COMPLETED', $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_complete_rejects_already_completed(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = Consultation::factory()->forTenant($tenant->id)->completed()->create([
            'patient_id' => $patient->id, 'chief_complaint' => 'Headache',
        ]);

        $this->expectException(ValidationException::class);
        $service->complete($consultation, $doctor);
    }

    public function test_amend_requires_reason_and_appends_audit(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $service = app(ConsultationService::class);
        $consultation = Consultation::factory()->forTenant($tenant->id)->completed()->create([
            'patient_id' => $patient->id,
            'chief_complaint' => 'Headache',
            'diagnosis_summary' => 'Migraine',
        ]);

        $amended = $service->amend($consultation, $doctor, 'Corrected dosage');

        $this->assertStringContainsString('Amendment: Corrected dosage', $amended->diagnosis_summary);
        $this->assertSame('COMPLETED', $amended->status);
    }

    public function test_amend_rejects_draft(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = Consultation::factory()->forTenant($tenant->id)->create([
            'patient_id' => $patient->id, 'status' => 'DRAFT',
        ]);

        $this->expectException(ValidationException::class);
        app(ConsultationService::class)->amend($consultation, $doctor, 'x');
    }

    public function test_for_patient_returns_history_ordered_desc(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $old = Consultation::factory()->forTenant($tenant->id)->create([
            'patient_id' => $patient->id, 'created_at' => now()->subDays(5),
        ]);
        $new = Consultation::factory()->forTenant($tenant->id)->create([
            'patient_id' => $patient->id, 'created_at' => now(),
        ]);

        $history = app(ConsultationService::class)->forPatient($patient->id);

        $this->assertCount(2, $history);
        $this->assertSame($new->id, $history->first()->id);
    }

    public function test_validate_system_specific_ayurveda(): void
    {
        [$tenant, $owner] = $this->seedTenant();

        $validated = app(ConsultationService::class)->validateSystemSpecific('AYURVEDA', [
            'prakriti' => 'Vata-Pitta',
            'agni' => 'Tikshna',
            'dosha' => 'Vata',
        ]);

        $this->assertSame('Vata-Pitta', $validated['prakriti']);
        $this->assertSame('Tikshna', $validated['agni']);
    }

    public function test_validate_system_specific_siddha(): void
    {
        app(TenantContext::class)->set(Tenant::factory()->create()->id);

        $validated = app(ConsultationService::class)->validateSystemSpecific('SIDDHA', [
            'mukkutram' => 'Vata',
            'naadi' => 'Vata-Pitta',
            'envagai_thervu' => 'Naadi, Naa, Sparisam, Niram',
        ]);

        $this->assertSame('Vata', $validated['mukkutram']);
    }

    public function test_validate_system_specific_homeopathy(): void
    {
        app(TenantContext::class)->set(Tenant::factory()->create()->id);

        $validated = app(ConsultationService::class)->validateSystemSpecific('HOMEOPATHY', [
            'remedy' => 'Belladonna 30',
            'constitution' => 'Sanguine',
            'modalities' => 'worse noise',
        ]);

        $this->assertSame('Belladonna 30', $validated['remedy']);
    }

    public function test_validate_system_specific_general_returns_empty(): void
    {
        app(TenantContext::class)->set(Tenant::factory()->create()->id);

        $validated = app(ConsultationService::class)->validateSystemSpecific('GENERAL', []);

        $this->assertSame([], $validated);
    }

    public function test_consultations_are_tenant_scoped(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        Consultation::factory()->forTenant($tenant->id)->create(['patient_id' => $patient->id]);

        // Switch to a different tenant.
        $otherTenant = Tenant::factory()->create();
        app(TenantContext::class)->set($otherTenant->id);

        $history = app(ConsultationService::class)->forPatient($patient->id);
        $this->assertCount(0, $history);
    }

    public function test_complete_syncs_in_consultation_appointment_to_completed(): void
    {
        [$tenant, $owner, $doctor] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'user_id' => $doctor->id,
            'status' => 'IN_CONSULTATION',
        ]);
        $service = app(ConsultationService::class);
        $consultation = $service->start(['patient_id' => $patient->id, 'appointment_id' => $appointment->id], $doctor);
        $service->update($consultation, ['chief_complaint' => 'Fever'], $doctor);

        $service->complete($consultation, $doctor);

        $this->assertSame('COMPLETED', $appointment->fresh()->status);
    }
}
