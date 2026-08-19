<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\AiRequest;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\IpdAdmission;
use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TreatmentBooking;
use App\Models\TreatmentService;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adversarial tenant-isolation matrix: a tenant-A user attempts to read
 * and mutate tenant-B resources across every major API resource. Every
 * attempt must be blocked (404 — the global scope hides the row before
 * any policy runs) and must not leak data.
 */
class TenantAdversarialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $attacker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->attacker = User::factory()->forTenant($this->tenantA)->role('CLINIC_OWNER')->create();
    }

    private function headers(): array
    {
        $issued = app(TokenService::class)->create($this->attacker, 'adversarial', ['*']);

        return ['Authorization' => 'Bearer '.$issued['token']];
    }

    /**
     * Build one tenant-B-owned instance of every major resource. Context is
     * switched to tenant B for creation, then restored to null.
     *
     * @return array<string, int> resource label → tenant-B id
     */
    private function tenantBResources(): array
    {
        $ctx = app(TenantContext::class);
        $ctx->set($this->tenantB->id);

        $patientB = Patient::factory()->create(['tenant_id' => $this->tenantB->id]);
        $doctorB = User::factory()->forTenant($this->tenantB)->role('DOCTOR')->create();

        $appointmentB = Appointment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'patient_id' => $patientB->id,
            'user_id' => $doctorB->id,
        ]);

        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'patient_id' => $patientB->id,
        ]);

        $serviceB = TreatmentService::factory()->create(['tenant_id' => $this->tenantB->id]);
        $treatmentB = TreatmentBooking::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'patient_id' => $patientB->id,
            'treatment_service_id' => $serviceB->id,
        ]);

        $wardB = IpdWard::create(['tenant_id' => $this->tenantB->id, 'name' => 'Ward B', 'code' => 'WB']);
        $roomB = IpdRoom::create(['tenant_id' => $this->tenantB->id, 'ipd_ward_id' => $wardB->id, 'room_number' => 'B1']);
        $bedB = IpdBed::create(['tenant_id' => $this->tenantB->id, 'ipd_room_id' => $roomB->id, 'bed_number' => 'B1']);
        $ipdB = IpdAdmission::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'patient_id' => $patientB->id,
            'ipd_bed_id' => $bedB->id,
        ]);

        $aiRequestB = AiRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $doctorB->id,
            'output_status' => 'DRAFT',
            'output' => 'tenant B secret output',
        ]);

        $ctx->forget();

        return [
            'patients' => $patientB->id,
            'appointments' => $appointmentB->id,
            'invoices' => $invoiceB->id,
            'treatments' => $treatmentB->id,
            'ipd-admissions' => $ipdB->id,
            'ai-requests' => $aiRequestB->id,
        ];
    }

    public function test_cross_tenant_reads_return_404_for_every_major_resource(): void
    {
        $resources = $this->tenantBResources();

        $headers = $this->headers();

        foreach ($resources as $resource => $id) {
            $response = $this->withHeaders($headers)->getJson("/api/v1/{$resource}/{$id}");

            $this->assertContains(
                $response->status(),
                [403, 404],
                "GET /api/v1/{$resource}/{$id} returned {$response->status()} — cross-tenant read leaked"
            );
            $this->assertStringNotContainsString(
                'secret',
                $response->getContent(),
                "GET /api/v1/{$resource}/{$id} leaked tenant-B data in an error body"
            );
        }
    }

    public function test_cross_tenant_mutations_are_blocked(): void
    {
        $resources = $this->tenantBResources();

        $headers = $this->headers();

        // Update attempt (patient). Send a fully-valid payload so validation
        // can't mask the authorization result; blocked = 403 (policy) or 404
        // (scoped binding). Never 200.
        $update = $this->withHeaders($headers)->putJson("/api/v1/patients/{$resources['patients']}", [
            'first_name' => 'Hacked', 'last_name' => 'X', 'phone' => '9000001234',
        ]);
        $this->assertContains($update->status(), [403, 404], 'cross-tenant patient update returned '.$update->status());
        // Cancel attempt (appointment).
        $this->withHeaders($headers)->postJson("/api/v1/appointments/{$resources['appointments']}/cancel")
            ->assertNotFound();
        // Issue attempt (invoice).
        $this->withHeaders($headers)->postJson("/api/v1/invoices/{$resources['invoices']}/issue")
            ->assertNotFound();
        // Complete attempt (treatment).
        $this->withHeaders($headers)->postJson("/api/v1/treatments/{$resources['treatments']}/complete")
            ->assertNotFound();
        // Discharge attempt (IPD).
        $this->withHeaders($headers)->postJson("/api/v1/ipd-admissions/{$resources['ipd-admissions']}/discharge", [
            'discharge_diagnosis' => 'x', 'treatment_given' => 'x',
            'advice_on_discharge' => 'x', 'follow_up_instructions' => 'x', 'follow_up_days' => 7,
        ])->assertNotFound();
        // Approve attempt (AI request).
        $this->withHeaders($headers)->postJson("/api/v1/ai-requests/{$resources['ai-requests']}/approve")
            ->assertNotFound();

        // Nothing was actually mutated on tenant B's rows.
        $this->assertDatabaseMissing('patients', ['id' => $resources['patients'], 'first_name' => 'Hacked']);
        $this->assertSame('DRAFT', AiRequest::withoutGlobalScopes()->find($resources['ai-requests'])->output_status);
    }

    public function test_cross_tenant_listing_returns_only_own_tenant_rows(): void
    {
        $resources = $this->tenantBResources();

        // Tenant A's own patient.
        $ctx = app(TenantContext::class);
        $ctx->set($this->tenantA->id);
        $patientA = Patient::factory()->create(['tenant_id' => $this->tenantA->id]);
        $ctx->forget();

        $headers = $this->headers();

        $ids = collect($this->withHeaders($headers)->getJson('/api/v1/patients')->json('data'))->pluck('id');

        $this->assertContains($patientA->id, $ids);
        $this->assertNotContains($resources['patients'], $ids);
    }

    public function test_nested_cross_tenant_patient_subresources_are_blocked(): void
    {
        $resources = $this->tenantBResources();

        $headers = $this->headers();

        foreach (['prescriptions', 'followups', 'investigations'] as $sub) {
            $response = $this->withHeaders($headers)->getJson("/api/v1/patients/{$resources['patients']}/{$sub}");
            // Blocked outright OR 200 with an EMPTY list (nested index
            // endpoints are tenant-scoped via the global scope) — but never
            // tenant-B data.
            $this->assertContains($response->status(), [200, 403, 404], "{$sub} returned {$response->status()}");
            $this->assertEmpty($response->json('data') ?? [], "{$sub} leaked tenant-B rows");
        }
    }
}
