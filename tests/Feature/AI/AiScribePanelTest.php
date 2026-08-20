<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Livewire\EMR\AiScribePanel;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiScribePanelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    private function consultation(): Consultation
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        return Consultation::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $this->doctor->id,
            'chief_complaint' => 'Pain for 3 days',
            'status' => 'DRAFT',
        ]);
    }

    public function test_doctor_with_ai_permission_can_open_panel(): void
    {
        Livewire::actingAs($this->doctor)
            ->test(AiScribePanel::class, ['consultation' => $this->consultation()])
            ->assertStatus(200)
            ->assertSee('AI Scribe');
    }

    public function test_receptionist_without_ai_permission_gets_403(): void
    {
        $rec = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        Livewire::actingAs($rec)
            ->test(AiScribePanel::class, ['consultation' => $this->consultation()])
            ->assertStatus(403);
    }

    public function test_approve_requires_edit_fields_and_writes_consultation(): void
    {
        $consultation = $this->consultation();
        \App\Models\AiRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'contextable_type' => Consultation::class,
            'contextable_id' => $consultation->id,
            'output_status' => 'DRAFT',
            'output' => '{"subjective":"s","objective":"o","assessment":"a","plan":"p"}',
        ]);

        Livewire::actingAs($this->doctor)
            ->test(AiScribePanel::class, ['consultation' => $consultation])
            ->call('approve');

        $this->assertSame('s', $consultation->fresh()->history);
        $this->assertSame('p', $consultation->fresh()->treatment_plan);
    }
}
