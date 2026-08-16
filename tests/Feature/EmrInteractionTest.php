<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\EMR\ConsultationBoard;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmrInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_consultation_preserves_patient_id(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create(['email_verified_at' => now()]);

        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $consultation = Consultation::factory()->forTenant($tenant->id)->create([
            'patient_id' => $patient->id, 'user_id' => $owner->id, 'chief_complaint' => 'Cough',
        ]);

        Livewire::actingAs($owner)
            ->test(ConsultationBoard::class, ['patientId' => $patient->id])
            ->assertSet('patientId', $patient->id)
            ->call('openConsultation', $consultation->id)
            ->assertSet('patientId', $patient->id)
            ->assertSet('activeConsultationId', $consultation->id);
    }
}
