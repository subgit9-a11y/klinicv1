<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Models\AiFeature;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class ConfigurationPanel extends Component
{
    use WithPagination;

    public function boot(AuditService $audit): void
    {
        $this->audit = $audit;
    }

    protected AuditService $audit;

    public string $activeTab = 'plans';

    // Plan form
    public ?int $editingPlanId = null;
    public string $plan_name = '';
    public string $plan_code = '';
    public int $plan_price_cents = 0;
    public string $plan_billing_cycle = 'MONTHLY';
    public int $plan_max_users = 1;
    public int $plan_max_doctors = 1;
    public bool $plan_is_active = true;

    // Feature flag form
    public ?int $editingFeatureId = null;
    public string $feature_key = '';
    public string $feature_description = '';
    public bool $feature_is_global = true;
    public bool $feature_default_enabled = true;

    // AI model form
    public ?int $editingAiModelId = null;
    public string $ai_model_id = '';
    public string $ai_display_name = '';
    public string $ai_provider = 'gemini';
    public bool $ai_supports_vision = false;
    public bool $ai_supports_structured = false;
    public bool $ai_is_active = true;

    protected function rules(): array
    {
        return match ($this->activeTab) {
            'plans' => [
                'plan_name' => 'required|string|max:120',
                'plan_code' => 'required|string|max:48|unique:plans,code,' . ($this->editingPlanId ?? 'NULL'),
                'plan_price_cents' => 'required|integer|min:0',
                'plan_billing_cycle' => 'required|in:MONTHLY,YEARLY',
                'plan_max_users' => 'required|integer|min:1',
                'plan_max_doctors' => 'required|integer|min:1',
                'plan_is_active' => 'boolean',
            ],
            'features' => [
                'feature_key' => 'required|string|max:64|unique:feature_flags,key,' . ($this->editingFeatureId ?? 'NULL'),
                'feature_description' => 'nullable|string|max:255',
                'feature_is_global' => 'boolean',
                'feature_default_enabled' => 'boolean',
            ],
            'ai_models' => [
                'ai_model_id' => 'required|string|max:64',
                'ai_display_name' => 'required|string|max:120',
                'ai_provider' => 'required|string|max:32',
                'ai_supports_vision' => 'boolean',
                'ai_supports_structured' => 'boolean',
                'ai_is_active' => 'boolean',
            ],
            default => [],
        };
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetForm();
        $this->resetPage();
    }

    public function editPlan(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $this->editingPlanId = $id;
        $this->plan_name = $plan->name;
        $this->plan_code = $plan->code;
        $this->plan_price_cents = $plan->price_cents ?? 0;
        $this->plan_billing_cycle = $plan->billing_cycle ?? 'MONTHLY';
        $this->plan_max_users = $plan->max_users ?? 1;
        $this->plan_max_doctors = $plan->max_doctors ?? 1;
        $this->plan_is_active = $plan->is_active;
    }

    public function savePlan(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['plan_name'],
            'code' => $validated['plan_code'],
            'price_cents' => $validated['plan_price_cents'],
            'billing_cycle' => $validated['plan_billing_cycle'],
            'max_users' => $validated['plan_max_users'],
            'max_doctors' => $validated['plan_max_doctors'],
            'is_active' => $validated['plan_is_active'],
        ];

        if ($this->editingPlanId) {
            Plan::find($this->editingPlanId)?->update($data);
            $this->dispatch('plan-updated');
        } else {
            Plan::create($data);
            $this->dispatch('plan-created');
        }

        $this->resetForm();
    }

    public function deletePlan(int $id): void
    {
        Plan::find($id)?->delete();
        $this->dispatch('plan-deleted');
    }

    public function editFeature(int $id): void
    {
        $feature = FeatureFlag::findOrFail($id);
        $this->editingFeatureId = $id;
        $this->feature_key = $feature->key;
        $this->feature_description = $feature->description ?? '';
        $this->feature_is_global = $feature->is_global;
        $this->feature_default_enabled = $feature->default_enabled;
    }

    public function saveFeature(): void
    {
        $validated = $this->validate();

        $data = [
            'key' => $validated['feature_key'],
            'description' => $validated['feature_description'],
            'is_global' => $validated['feature_is_global'],
            'default_enabled' => $validated['feature_default_enabled'],
            'enabled' => $validated['feature_default_enabled'],
        ];

        if ($this->editingFeatureId) {
            FeatureFlag::find($this->editingFeatureId)?->update($data);
            $this->dispatch('feature-updated');
        } else {
            FeatureFlag::create($data);
            $this->dispatch('feature-created');
        }

        $this->audit->record('superadmin.feature_flag.saved', 'super_admin', ['after' => $data], null, auth()->user());

        $this->resetForm();
    }

    public function deleteFeature(int $id): void
    {
        FeatureFlag::find($id)?->delete();
        $this->audit->record('superadmin.feature_flag.deleted', 'super_admin', ['after' => ['id' => $id]], null, auth()->user());
        $this->dispatch('feature-deleted');
    }

    public function editAiModel(int $id): void
    {
        $model = AiModel::findOrFail($id);
        $this->editingAiModelId = $id;
        $this->ai_model_id = $model->model_id;
        $this->ai_display_name = $model->display_name;
        $this->ai_provider = $model->provider;
        $this->ai_supports_vision = $model->supports_vision;
        $this->ai_supports_structured = $model->supports_structured;
        $this->ai_is_active = $model->is_active;
    }

    public function saveAiModel(): void
    {
        $validated = $this->validate();

        $data = [
            'model_id' => $validated['ai_model_id'],
            'display_name' => $validated['ai_display_name'],
            'provider' => $validated['ai_provider'],
            'supports_vision' => $validated['ai_supports_vision'],
            'supports_structured' => $validated['ai_supports_structured'],
            'is_active' => $validated['ai_is_active'],
        ];

        if ($this->editingAiModelId) {
            AiModel::find($this->editingAiModelId)?->update($data);
            $this->dispatch('ai-model-updated');
        } else {
            AiModel::create($data);
            $this->dispatch('ai-model-created');
        }

        $this->resetForm();
    }

    public function deleteAiModel(int $id): void
    {
        AiModel::find($id)?->delete();
        $this->dispatch('ai-model-deleted');
    }

    public function clearCache(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        $this->dispatch('cache-cleared');
    }

    public function resetForm(): void
    {
        $this->editingPlanId = null;
        $this->plan_name = '';
        $this->plan_code = '';
        $this->plan_price_cents = 0;
        $this->plan_billing_cycle = 'MONTHLY';
        $this->plan_max_users = 1;
        $this->plan_max_doctors = 1;
        $this->plan_is_active = true;

        $this->editingFeatureId = null;
        $this->feature_key = '';
        $this->feature_description = '';
        $this->feature_is_global = true;
        $this->feature_default_enabled = true;

        $this->editingAiModelId = null;
        $this->ai_model_id = '';
        $this->ai_display_name = '';
        $this->ai_provider = 'gemini';
        $this->ai_supports_vision = false;
        $this->ai_supports_structured = false;
        $this->ai_is_active = true;
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);

        $plans = Plan::orderBy('price_cents')->paginate(10, ['*'], 'plansPage');
        $features = FeatureFlag::orderBy('key')->paginate(10, ['*'], 'featuresPage');
        $aiModels = AiModel::orderBy('provider')->orderBy('model_id')->paginate(10, ['*'], 'aiModelsPage');
        $aiFeatures = AiFeature::orderBy('category')->orderBy('key')->get();

        return view('livewire.super-admin.configuration-panel', [
            'plans' => $plans,
            'features' => $features,
            'aiModels' => $aiModels,
            'aiFeatures' => $aiFeatures,
        ])->layout('components.layouts.app');
    }
}
