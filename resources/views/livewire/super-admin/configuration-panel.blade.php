<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Super Admin Configuration</h1>
        <p class="text-sm text-gray-600">Manage plans, feature flags, AI models, and system settings.</p>
    </div>

    <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200">
        <button wire:click="setTab('plans')"
            class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'plans' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
            Plans
        </button>
        <button wire:click="setTab('features')"
            class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'features' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
            Feature Flags
        </button>
        <button wire:click="setTab('ai_models')"
            class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'ai_models' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
            AI Models
        </button>
        <button wire:click="setTab('system')"
            class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'system' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
            System
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($activeTab === 'plans')
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-semibold mb-4">{{ $editingPlanId ? 'Edit Plan' : 'Create Plan' }}</h2>
                <form wire:submit="savePlan" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input wire:model="plan_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                        @error('plan_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                        <input wire:model="plan_code" type="text" class="w-full rounded border-gray-300 shadow-sm uppercase">
                        @error('plan_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price (in paise)</label>
                        <input wire:model="plan_price_cents" type="number" class="w-full rounded border-gray-300 shadow-sm">
                        @error('plan_price_cents') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
                        <select wire:model="plan_billing_cycle" class="w-full rounded border-gray-300 shadow-sm">
                            <option value="MONTHLY">Monthly</option>
                            <option value="YEARLY">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Users</label>
                        <input wire:model="plan_max_users" type="number" min="1" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Doctors</label>
                        <input wire:model="plan_max_doctors" type="number" min="1" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="flex items-center gap-2">
                            <input wire:model="plan_is_active" type="checkbox" class="rounded border-gray-300">
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="col-span-2 flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            {{ $editingPlanId ? 'Update' : 'Create' }}
                        </button>
                        @if ($editingPlanId)
                            <button type="button" wire:click="resetForm" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">Cancel</button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cycle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="px-4 py-2 text-sm">{{ $plan->name }}</td>
                                <td class="px-4 py-2 text-sm font-mono">{{ $plan->code }}</td>
                                <td class="px-4 py-2 text-sm">₹{{ number_format($plan->price_cents / 100, 2) }}</td>
                                <td class="px-4 py-2 text-sm">{{ $plan->billing_cycle }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $plan->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <button wire:click="editPlan({{ $plan->id }})" class="text-indigo-600 hover:underline mr-2">Edit</button>
                                    <button wire:click="deletePlan({{ $plan->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4">{{ $plans->links() }}</div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'features')
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-semibold mb-4">{{ $editingFeatureId ? 'Edit Feature Flag' : 'Create Feature Flag' }}</h2>
                <form wire:submit="saveFeature" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Key</label>
                        <input wire:model="feature_key" type="text" class="w-full rounded border-gray-300 shadow-sm">
                        @error('feature_key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input wire:model="feature_description" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div class="col-span-2 flex gap-4">
                        <label class="flex items-center gap-2">
                            <input wire:model="feature_is_global" type="checkbox" class="rounded border-gray-300">
                            <span class="text-sm">Global</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input wire:model="feature_default_enabled" type="checkbox" class="rounded border-gray-300">
                            <span class="text-sm">Default Enabled</span>
                        </label>
                    </div>
                    <div class="col-span-2 flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            {{ $editingFeatureId ? 'Update' : 'Create' }}
                        </button>
                        @if ($editingFeatureId)
                            <button type="button" wire:click="resetForm" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">Cancel</button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Key</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Global</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($features as $feature)
                            <tr>
                                <td class="px-4 py-2 text-sm font-mono">{{ $feature->key }}</td>
                                <td class="px-4 py-2 text-sm">{{ $feature->description }}</td>
                                <td class="px-4 py-2 text-sm">{{ $feature->is_global ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-2 text-sm">{{ $feature->default_enabled ? 'On' : 'Off' }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <button wire:click="editFeature({{ $feature->id }})" class="text-indigo-600 hover:underline mr-2">Edit</button>
                                    <button wire:click="deleteFeature({{ $feature->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4">{{ $features->links() }}</div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'ai_models')
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-semibold mb-4">{{ $editingAiModelId ? 'Edit AI Model' : 'Register AI Model' }}</h2>
                <form wire:submit="saveAiModel" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Model ID</label>
                        <input wire:model="ai_model_id" type="text" placeholder="gemini-2.0-flash" class="w-full rounded border-gray-300 shadow-sm">
                        @error('ai_model_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                        <input wire:model="ai_display_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                        <input wire:model="ai_provider" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div class="flex gap-4 items-end">
                        <label class="flex items-center gap-2">
                            <input wire:model="ai_supports_vision" type="checkbox" class="rounded border-gray-300">
                            <span class="text-sm">Vision</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input wire:model="ai_supports_structured" type="checkbox" class="rounded border-gray-300">
                            <span class="text-sm">Structured</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input wire:model="ai_is_active" type="checkbox" class="rounded border-gray-300">
                            <span class="text-sm">Active</span>
                        </label>
                    </div>
                    <div class="col-span-2 flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            {{ $editingAiModelId ? 'Update' : 'Register' }}
                        </button>
                        @if ($editingAiModelId)
                            <button type="button" wire:click="resetForm" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">Cancel</button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Model ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Display Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Capabilities</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($aiModels as $model)
                            <tr>
                                <td class="px-4 py-2 text-sm">{{ $model->provider }}</td>
                                <td class="px-4 py-2 text-sm font-mono">{{ $model->model_id }}</td>
                                <td class="px-4 py-2 text-sm">{{ $model->display_name }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if($model->supports_vision)<span class="text-xs bg-blue-100 text-blue-700 px-1 rounded mr-1">Vision</span>@endif
                                    @if($model->supports_structured)<span class="text-xs bg-purple-100 text-purple-700 px-1 rounded">Structured</span>@endif
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <button wire:click="editAiModel({{ $model->id }})" class="text-indigo-600 hover:underline mr-2">Edit</button>
                                    <button wire:click="deleteAiModel({{ $model->id }})" wire:confirm="Are you sure?" class="text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4">{{ $aiModels->links() }}</div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-semibold mb-4">AI Features</h2>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($aiFeatures as $aiFeature)
                        <div class="p-3 border rounded">
                            <div class="font-medium text-sm">{{ $aiFeature->name }}</div>
                            <div class="text-xs text-gray-500">{{ $aiFeature->key }} · {{ $aiFeature->category }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'system')
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">System Actions</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 border rounded">
                    <div>
                        <div class="font-medium text-sm">Clear Application Cache</div>
                        <div class="text-xs text-gray-500">Flushes cache and config cache.</div>
                    </div>
                    <button wire:click="clearCache" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600">
                        Clear Cache
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
