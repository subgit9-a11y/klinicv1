<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Super Admin AI prompt management: prompt catalogue + version history.
 * The highest version number is the active one (AIManager::resolvePromptVersion),
 * so "publish" = create a new version; no separate activation flag.
 */
class AiPromptManagement extends Component
{
    public bool $showPromptForm = false;
    public string $key = '';
    public string $name = '';
    public string $system = 'GENERAL';

    public ?int $versionPromptId = null;
    public string $system_prompt = '';
    public string $user_prompt_template = '';
    public ?string $default_model = null;

    public function createPrompt(): void
    {
        $this->guard();

        $data = $this->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/', 'unique:ai_prompts,key'],
            'name' => ['required', 'string', 'max:120'],
            'system' => ['required', 'in:GENERAL,AYURVEDA,SIDDHA,HOMEOPATHY'],
        ]);

        AiPrompt::create($data + ['is_active' => true]);

        $this->reset(['showPromptForm', 'key', 'name', 'system']);
        session()->flash('message', 'Prompt created.');
    }

    public function togglePrompt(int $promptId): void
    {
        $this->guard();

        $prompt = AiPrompt::findOrFail($promptId);
        $prompt->update(['is_active' => ! $prompt->is_active]);
    }

    public function startVersion(int $promptId): void
    {
        $this->guard();

        $prompt = AiPrompt::with('versions')->findOrFail($promptId);
        $active = $prompt->activeVersion();

        $this->versionPromptId = $prompt->id;
        $this->system_prompt = (string) ($active?->system_prompt ?? '');
        $this->user_prompt_template = (string) ($active?->user_prompt_template ?? '');
        $this->default_model = $active?->default_model;
    }

    public function publishVersion(): void
    {
        $this->guard();

        $data = $this->validate([
            'versionPromptId' => ['required', 'exists:ai_prompts,id'],
            'system_prompt' => ['nullable', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'default_model' => ['nullable', 'string', 'max:64'],
        ]);

        $prompt = AiPrompt::with('versions')->findOrFail($data['versionPromptId']);
        $nextVersion = (int) ($prompt->versions->max('version') ?? 0) + 1;

        DB::transaction(function () use ($prompt, $nextVersion, $data) {
            AiPromptVersion::create([
                'ai_prompt_id' => $prompt->id,
                'version' => $nextVersion,
                'system_prompt' => $data['system_prompt'] !== '' ? $data['system_prompt'] : null,
                'user_prompt_template' => $data['user_prompt_template'],
                'default_model' => $data['default_model'],
                'created_by' => auth()->id(),
            ]);
        });

        $this->reset(['versionPromptId', 'system_prompt', 'user_prompt_template', 'default_model']);
        session()->flash('message', "Version {$nextVersion} published (now active).");
    }

    public function render()
    {
        $this->guard();

        return view('livewire.super-admin.ai-prompt-management', [
            'prompts' => AiPrompt::with('versions')->orderBy('key')->get(),
            'systems' => ['GENERAL', 'AYURVEDA', 'SIDDHA', 'HOMEOPATHY'],
        ])->layout('components.layouts.app');
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
    }
}
