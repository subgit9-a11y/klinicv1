<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Livewire\Onboarding\ClinicSignup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    public function test_signup_page_renders_for_guests(): void
    {
        Livewire::test(ClinicSignup::class)
            ->assertStatus(200)
            ->assertSee('Set up your clinic')
            ->assertSee('Clinic name');
    }

    public function test_authenticated_users_are_redirected_from_signup(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        $this->actingAs($user)->get('/signup')->assertRedirect();
    }

    public function test_step_navigation_validates_each_step(): void
    {
        Livewire::test(ClinicSignup::class)
            ->call('next') // step 1 without clinic name → validation error
            ->assertHasErrors(['clinic_name'])
            ->set('clinic_name', 'Shree Ayurveda')
            ->call('next')
            ->assertSet('step', 2)
            ->call('next')
            ->assertSet('step', 3)
            ->call('submit')
            ->assertHasErrors(['owner_name', 'owner_email', 'owner_password']);
    }

    public function test_full_signup_creates_tenant_owner_subscription_and_logs_in(): void
    {
        Livewire::test(ClinicSignup::class)
            ->set('clinic_name', 'Wellness Centre')
            ->set('system', 'AYURVEDA')
            ->set('plan_code', 'SMALL_CLINIC')
            ->set('owner_name', 'Dr. Rao')
            ->set('owner_email', 'rao@wellness.test')
            ->set('owner_password', 'secret123')
            ->set('owner_password_confirmation', 'secret123')
            ->call('submit')
            ->assertRedirect(route('onboarding.setup'));

        $tenant = Tenant::where('name', 'Wellness Centre')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('TRIAL', $tenant->status);
        $this->assertSame('SMALL_CLINIC', $tenant->plan_code);
        $this->assertNotNull($tenant->trial_ends_at);

        $owner = User::where('email', 'rao@wellness.test')->first();
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame('CLINIC_OWNER', $owner->role);
        $this->assertAuthenticatedAs($owner);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_duplicate_owner_email_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create(['email' => 'taken@clinic.test']);

        Livewire::test(ClinicSignup::class)
            ->set('clinic_name', 'Another Clinic')
            ->set('owner_name', 'X')
            ->set('owner_email', 'taken@clinic.test')
            ->set('owner_password', 'secret123')
            ->set('owner_password_confirmation', 'secret123')
            ->call('submit')
            ->assertHasErrors(['owner_email']);
    }

    public function test_password_mismatch_is_rejected(): void
    {
        Livewire::test(ClinicSignup::class)
            ->set('clinic_name', 'Mismatch Clinic')
            ->set('owner_name', 'X')
            ->set('owner_email', 'mismatch@test.dev')
            ->set('owner_password', 'secret123')
            ->set('owner_password_confirmation', 'different99')
            ->call('submit')
            ->assertHasErrors(['owner_password']);
    }
}
