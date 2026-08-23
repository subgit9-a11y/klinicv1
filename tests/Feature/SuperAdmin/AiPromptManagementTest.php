<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\AiPromptManagement;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiPromptManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_super_admin_lists_prompts_with_versions(): void
    {
        $prompt = AiPrompt::create(['key' => 'test.prompt', 'name' => 'Test', 'system' => 'GENERAL']);
        AiPromptVersion::create([
            'ai_prompt_id' => $prompt->id,
            'version' => 1,
            'user_prompt_template' => 'Summarize {{data}}',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(AiPromptManagement::class)
            ->assertSee('test.prompt')
            ->assertSee('v1');
    }

    public function test_super_admin_creates_prompt(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(AiPromptManagement::class)
            ->set('key', 'new.prompt')
            ->set('name', 'New Prompt')
            ->set('system', 'AYURVEDA')
            ->call('createPrompt')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ai_prompts', ['key' => 'new.prompt', 'system' => 'AYURVEDA', 'is_active' => true]);
    }

    public function test_prompt_key_must_be_unique(): void
    {
        AiPrompt::create(['key' => 'dup.key', 'name' => 'One', 'system' => 'GENERAL']);

        Livewire::actingAs($this->superAdmin)
            ->test(AiPromptManagement::class)
            ->set('key', 'dup.key')
            ->set('name', 'Two')
            ->call('createPrompt')
            ->assertHasErrors(['key']);
    }

    public function test_publish_version_increments_and_becomes_active(): void
    {
        $prompt = AiPrompt::create(['key' => 'v.prompt', 'name' => 'V', 'system' => 'GENERAL']);
        AiPromptVersion::create([
            'ai_prompt_id' => $prompt->id,
            'version' => 1,
            'user_prompt_template' => 'Old {{x}}',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(AiPromptManagement::class)
            ->call('startVersion', $prompt->id)
            ->set('user_prompt_template', 'New {{x}}')
            ->set('default_model', 'gemini-2.0-flash')
            ->call('publishVersion')
            ->assertHasNoErrors();

        $prompt->refresh();
        $this->assertSame(2, $prompt->versions()->count());
        $active = $prompt->activeVersion();
        $this->assertSame(2, $active->version);
        $this->assertSame('New {{x}}', $active->user_prompt_template);
        $this->assertSame('gemini-2.0-flash', $active->default_model);
    }

    public function test_publish_version_prefills_current_template(): void
    {
        $prompt = AiPrompt::create(['key' => 'prefill', 'name' => 'P', 'system' => 'GENERAL']);
        AiPromptVersion::create([
            'ai_prompt_id' => $prompt->id,
            'version' => 1,
            'system_prompt' => 'You are a clinician.',
            'user_prompt_template' => 'Draft {{chief_complaint}}',
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(AiPromptManagement::class)
            ->call('startVersion', $prompt->id)
            ->assertSet('user_prompt_template', 'Draft {{chief_complaint}}')
            ->assertSet('system_prompt', 'You are a clinician.');
    }

    public function test_toggle_prompt_disables_resolution(): void
    {
        $prompt = AiPrompt::create(['key' => 'toggle.me', 'name' => 'T', 'system' => 'GENERAL']);

        Livewire::actingAs($this->superAdmin)
            ->test(AiPromptManagement::class)
            ->call('togglePrompt', $prompt->id);

        $this->assertFalse($prompt->fresh()->is_active);

        // A disabled prompt no longer resolves for AI generation.
        $manager = app(\App\Services\AI\AIManager::class);
        $method = new \ReflectionMethod($manager, 'resolvePromptVersion');
        $method->setAccessible(true);
        $this->assertNull($method->invoke($manager, 'toggle.me', null));
    }

    public function test_clinic_owner_gets_403(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $owner = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        Livewire::actingAs($owner)
            ->test(AiPromptManagement::class)
            ->assertForbidden();
    }
}
