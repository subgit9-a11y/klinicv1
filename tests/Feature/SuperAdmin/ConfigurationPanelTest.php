<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\ConfigurationPanel;
use App\Models\AiModel;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfigurationPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_configuration_panel(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->assertStatus(200)
            ->assertSee('Super Admin Configuration');
    }

    public function test_non_super_admin_cannot_access_configuration_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->assertStatus(403);
    }

    public function test_can_create_plan_via_configuration_panel(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->set('plan_name', 'Enterprise')
            ->set('plan_code', 'ENTERPRISE')
            ->set('plan_price_cents', 500000)
            ->set('plan_billing_cycle', 'MONTHLY')
            ->set('plan_max_users', 50)
            ->set('plan_max_doctors', 20)
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plans', [
            'code' => 'ENTERPRISE',
            'name' => 'Enterprise',
            'price_cents' => 500000,
        ]);
    }

    public function test_can_edit_plan_via_configuration_panel(): void
    {
        $user = User::factory()->superAdmin()->create();
        $plan = Plan::factory()->create(['code' => 'BASIC', 'name' => 'Basic']);

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('editPlan', $plan->id)
            ->assertSet('plan_name', 'Basic')
            ->set('plan_name', 'Basic Plus')
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Basic Plus',
        ]);
    }

    public function test_can_delete_plan_via_configuration_panel(): void
    {
        $user = User::factory()->superAdmin()->create();
        $plan = Plan::factory()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('deletePlan', $plan->id);

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_can_create_feature_flag(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('setTab', 'features')
            ->set('feature_key', 'ai_scribe')
            ->set('feature_description', 'AI clinical scribe')
            ->set('feature_is_global', true)
            ->set('feature_default_enabled', true)
            ->call('saveFeature')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'ai_scribe',
            'description' => 'AI clinical scribe',
        ]);
    }

    public function test_can_edit_feature_flag(): void
    {
        $user = User::factory()->superAdmin()->create();
        $flag = FeatureFlag::factory()->create([
            'key' => 'treatments',
            'description' => 'Treatment module',
            'default_enabled' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('setTab', 'features')
            ->call('editFeature', $flag->id)
            ->assertSet('feature_key', 'treatments')
            ->set('feature_default_enabled', true)
            ->call('saveFeature')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('feature_flags', [
            'id' => $flag->id,
            'default_enabled' => true,
        ]);
    }

    public function test_can_delete_feature_flag(): void
    {
        $user = User::factory()->superAdmin()->create();
        $flag = FeatureFlag::factory()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('setTab', 'features')
            ->call('deleteFeature', $flag->id);

        $this->assertDatabaseMissing('feature_flags', ['id' => $flag->id]);
    }

    public function test_can_register_ai_model(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('setTab', 'ai_models')
            ->set('ai_model_id', 'gemini-2.5-pro')
            ->set('ai_display_name', 'Gemini 2.5 Pro')
            ->set('ai_provider', 'gemini')
            ->set('ai_supports_vision', true)
            ->set('ai_supports_structured', true)
            ->call('saveAiModel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ai_models', [
            'model_id' => 'gemini-2.5-pro',
            'display_name' => 'Gemini 2.5 Pro',
        ]);
    }

    public function test_can_edit_ai_model(): void
    {
        $user = User::factory()->superAdmin()->create();
        $aiModel = AiModel::factory()->create([
            'model_id' => 'gemini-1.5-flash',
            'display_name' => 'Gemini 1.5 Flash',
            'supports_vision' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('setTab', 'ai_models')
            ->call('editAiModel', $aiModel->id)
            ->assertSet('ai_model_id', 'gemini-1.5-flash')
            ->set('ai_supports_vision', true)
            ->call('saveAiModel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ai_models', [
            'id' => $aiModel->id,
            'supports_vision' => true,
        ]);
    }

    public function test_can_delete_ai_model(): void
    {
        $user = User::factory()->superAdmin()->create();
        $aiModel = AiModel::factory()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->call('setTab', 'ai_models')
            ->call('deleteAiModel', $aiModel->id);

        $this->assertDatabaseMissing('ai_models', ['id' => $aiModel->id]);
    }

    public function test_tab_switching_resets_form(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(ConfigurationPanel::class)
            ->set('plan_name', 'Some Name')
            ->call('setTab', 'features')
            ->assertSet('activeTab', 'features')
            ->assertSet('plan_name', '');
    }
}
