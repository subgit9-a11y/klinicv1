<?php

declare(strict_types=1);

namespace Tests\Feature\EMR;

use App\Livewire\EMR\ConsultationBoard;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EMR\ConsultationService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConsultationBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create(['email_verified_at' => now()]);

        return [$tenant, $owner];
    }

    public function test_board_renders_without_patient_selected(): void
    {
        [$tenant, $owner] = $this->seedTenant();

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->assertStatus(200)
            ->assertSee(__('klinic360.emr.title'));
    }

    public function test_select_patient_loads_history(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = Consultation::factory()->forTenant($tenant->id)->create([
            'patient_id' => $patient->id, 'user_id' => $owner->id, 'chief_complaint' => 'Cough',
        ]);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->call('selectPatient', $patient->id)
            ->assertSee($owner->name)
            ->assertSee(__('klinic360.emr.draft'));
    }

    public function test_start_consultation_creates_draft(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->set('patientId', $patient->id)
            ->set('medicineSystem', 'AYURVEDA')
            ->call('startConsultation')
            ->assertSet('activeConsultationId', fn ($v) => $v !== null);

        $this->assertDatabaseHas('consultations', [
            'patient_id' => $patient->id,
            'medicine_system' => 'AYURVEDA',
            'status' => 'DRAFT',
        ]);
    }

    public function test_save_draft_persists_soap_fields(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = app(ConsultationService::class)->start(['patient_id' => $patient->id], $owner);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->call('openConsultation', $consultation->id)
            ->set('chiefComplaint', 'Knee pain')
            ->set('diagnosisSummary', 'Osteoarthritis')
            ->call('saveDraft');

        $this->assertSame('Knee pain', $consultation->fresh()->chief_complaint);
        $this->assertSame('Osteoarthritis', $consultation->fresh()->diagnosis_summary);
        $this->assertSame('DRAFT', $consultation->fresh()->status);
    }

    public function test_complete_consultation_transitions_to_completed(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = app(ConsultationService::class)->start(['patient_id' => $patient->id], $owner);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->call('openConsultation', $consultation->id)
            ->set('chiefComplaint', 'Fever')
            ->call('completeConsultation');

        $this->assertSame('COMPLETED', $consultation->fresh()->status);
        $this->assertNotNull($consultation->fresh()->completed_at);
    }

    public function test_add_diagnosis_via_board(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = app(ConsultationService::class)->start(['patient_id' => $patient->id, 'medicine_system' => 'AYURVEDA'], $owner);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->call('openConsultation', $consultation->id)
            ->set('dxName', 'Sandhivata')
            ->set('dxType', 'PRIMARY')
            ->call('addDiagnosis');

        $this->assertDatabaseHas('diagnoses', [
            'consultation_id' => $consultation->id,
            'name' => 'Sandhivata',
            'system' => 'AYURVEDA',
        ]);
    }

    public function test_add_note_via_board(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = app(ConsultationService::class)->start(['patient_id' => $patient->id], $owner);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->call('openConsultation', $consultation->id)
            ->set('noteContent', 'Patient responding well')
            ->set('noteType', 'PROGRESS')
            ->call('addNote');

        $this->assertDatabaseHas('clinical_notes', [
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'content' => 'Patient responding well',
        ]);
    }

    public function test_amend_completed_consultation_via_board(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = Consultation::factory()->forTenant($tenant->id)->completed()->create([
            'patient_id' => $patient->id,
            'chief_complaint' => 'Headache',
            'diagnosis_summary' => 'Migraine',
        ]);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class)
            ->call('selectPatient', $patient->id)
            ->call('openConsultation', $consultation->id)
            ->call('openAmendModal')
            ->set('amendReason', 'Corrected dosage instructions')
            ->call('amendConsultation');

        $this->assertStringContainsString('Corrected dosage instructions', $consultation->fresh()->diagnosis_summary);
    }

    public function test_therapist_cannot_create_consultation(): void
    {
        [$tenant, $owner] = $this->seedTenant();
        $therapist = User::factory()->forTenant($tenant)->role('THERAPIST')->create(['email_verified_at' => now()]);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($therapist)
            ->test(ConsultationBoard::class)
            ->set('patientId', $patient->id)
            ->call('startConsultation')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $this->get('/emr')->assertRedirect('/login');
    }
}
