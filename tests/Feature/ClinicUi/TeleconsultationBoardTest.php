<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\Telemedicine\TeleconsultationBoard;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeleconsultationBoardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
        $this->receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_schedule_start_end_lifecycle(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->receptionist)
            ->test(TeleconsultationBoard::class)
            ->set('patient_id', $patient->id)
            ->call('schedule');

        $tc = Teleconsultation::sole();
        $this->assertSame('SCHEDULED', $tc->status);

        Livewire::actingAs($this->receptionist)
            ->test(TeleconsultationBoard::class)
            ->call('start', $tc->id);
        $this->assertSame('STARTED', $tc->fresh()->status);

        Livewire::actingAs($this->receptionist)
            ->test(TeleconsultationBoard::class)
            ->call('end', $tc->id);
        $this->assertSame('COMPLETED', $tc->fresh()->status);
    }

    public function test_cancel_marks_cancelled(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->receptionist)
            ->test(TeleconsultationBoard::class)
            ->set('patient_id', $patient->id)
            ->call('schedule');

        $tc = Teleconsultation::sole();

        Livewire::actingAs($this->receptionist)
            ->test(TeleconsultationBoard::class)
            ->call('cancel', $tc->id);

        $this->assertSame('CANCELLED', $tc->fresh()->status);
    }
}
