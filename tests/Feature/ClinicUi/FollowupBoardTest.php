<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\EMR\FollowupBoard;
use App\Models\Followup;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class FollowupBoardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();
        $this->receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    private function followup(array $attrs = []): Followup
    {
        return Followup::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ], $attrs));
    }

    public function test_schedule_creates_pending_followup(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::actingAs($this->doctor)
            ->test(FollowupBoard::class)
            ->set('patient_id', $patient->id)
            ->set('due_date', Carbon::tomorrow()->toDateString())
            ->set('instructions', 'Review report')
            ->call('schedule');

        $followup = Followup::sole();
        $this->assertSame('PENDING', $followup->status);
        $this->assertSame($patient->id, $followup->patient_id);
        $this->assertSame('Review report', $followup->instructions);
    }

    public function test_complete_and_cancel_update_status(): void
    {
        $a = $this->followup();
        $b = $this->followup();

        Livewire::actingAs($this->doctor)->test(FollowupBoard::class)->call('complete', $a->id);
        Livewire::actingAs($this->doctor)->test(FollowupBoard::class)->call('cancel', $b->id);

        $this->assertSame('COMPLETED', $a->fresh()->status);
        $this->assertSame('CANCELLED', $b->fresh()->status);
    }

    public function test_reschedule_changes_due_date(): void
    {
        $followup = $this->followup(['due_date' => Carbon::today()->toDateString()]);
        $newDate = Carbon::today()->addWeek()->toDateString();

        Livewire::actingAs($this->doctor)
            ->test(FollowupBoard::class)
            ->call('startReschedule', $followup->id)
            ->set('rescheduleDate', $newDate)
            ->call('reschedule');

        $this->assertSame($newDate, $followup->fresh()->due_date->toDateString());
    }

    public function test_bucket_filters_partition_followups(): void
    {
        $today = $this->followup(['due_date' => Carbon::today()->toDateString()]);
        $upcoming = $this->followup(['due_date' => Carbon::today()->addDays(3)->toDateString()]);
        $missed = $this->followup(['due_date' => Carbon::today()->subDays(2)->toDateString()]);
        $done = $this->followup(['due_date' => Carbon::today()->toDateString(), 'status' => 'COMPLETED']);

        Livewire::actingAs($this->doctor)->test(FollowupBoard::class)
            ->set('bucket', 'today')->assertSee($today->patient->k360_uid)->assertDontSee($upcoming->patient->k360_uid);

        Livewire::actingAs($this->doctor)->test(FollowupBoard::class)
            ->set('bucket', 'upcoming')->assertSee($upcoming->patient->k360_uid);

        Livewire::actingAs($this->doctor)->test(FollowupBoard::class)
            ->set('bucket', 'missed')->assertSee($missed->patient->k360_uid);

        Livewire::actingAs($this->doctor)->test(FollowupBoard::class)
            ->set('bucket', 'completed')->assertSee($done->patient->k360_uid);
    }

    public function test_receptionist_cannot_view_the_board(): void
    {
        // RECEPTIONIST has no consultations.view permission, so even the
        // initial render is forbidden.
        Livewire::actingAs($this->receptionist)
            ->test(FollowupBoard::class)
            ->assertForbidden();
    }

    public function test_nurse_can_view_but_not_schedule_or_complete(): void
    {
        // NURSE has consultations.view but not create/edit.
        $nurse = User::factory()->forTenant($this->tenant)->role('NURSE')->create();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $followup = $this->followup();

        Livewire::actingAs($nurse)
            ->test(FollowupBoard::class)
            ->assertSee('Follow-ups');

        Livewire::actingAs($nurse)
            ->test(FollowupBoard::class)
            ->set('patient_id', $patient->id)
            ->set('due_date', Carbon::tomorrow()->toDateString())
            ->call('schedule')
            ->assertForbidden();

        Livewire::actingAs($nurse)
            ->test(FollowupBoard::class)
            ->call('complete', $followup->id)
            ->assertForbidden();

        $this->assertSame('PENDING', $followup->fresh()->status);
    }

    public function test_cross_tenant_followup_not_accessible(): void
    {
        $otherTenant = Tenant::factory()->create();
        $ctx = app(TenantContext::class);
        $ctx->set($otherTenant->id);
        $foreign = Followup::factory()->create([
            'tenant_id' => $otherTenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);
        $ctx->set($this->tenant->id);

        // Livewire bubbles ModelNotFoundException for cross-tenant ids (the
        // BelongsToTenant scope filters the row before the action runs).
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->doctor)
            ->test(FollowupBoard::class)
            ->call('complete', $foreign->id);
    }
}
