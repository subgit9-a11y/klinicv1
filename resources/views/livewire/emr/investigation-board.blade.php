<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Investigations</h1>
            <p class="text-sm text-gray-600">Order lab &amp; diagnostic tests, upload reports, record results.</p>
        </div>
        @can(\App\Services\Auth\Permissions::CONSULTATIONS_CREATE)
            <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                {{ $showForm ? 'Close' : '+ Order investigation' }}
            </button>
        @endcan
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Order investigation</h2>
            <form wire:submit="order" class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient</label>
                    <input wire:model.live.debounce.300ms="patientSearch" type="text" placeholder="Search by name or UID…" class="w-full rounded border-gray-300 shadow-sm">
                    @if ($patients->isNotEmpty())
                        <select wire:model="patient_id" class="w-full mt-1 rounded border-gray-300 shadow-sm">
                            <option value="">Select…</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->k360_uid }})</option>
                            @endforeach
                        </select>
                    @endif
                    @error('patient_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Test name</label>
                    <input wire:model="name" type="text" placeholder="e.g. Complete Blood Count" class="w-full rounded border-gray-300 shadow-sm">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select wire:model="category" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-3">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Order</button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'REQUESTED' => 'Requested', 'IN_PROGRESS' => 'In progress', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled'] as $key => $label)
            <button wire:click="$set('statusFilter', '{{ $key }}')"
                    class="px-3 py-1.5 rounded-full text-sm font-medium {{ $statusFilter === $key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
        <select wire:model.live="categoryFilter" class="ml-auto rounded border-gray-300 shadow-sm text-sm">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}">{{ $cat }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Test</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Results</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($items as $inv)
                    <tr class="cursor-pointer hover:bg-gray-50" wire:click="openDetail({{ $inv->id }})">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $inv->requested_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="font-medium text-gray-900">{{ $inv->patient->first_name }} {{ $inv->patient->last_name }}</span>
                            <span class="block text-xs text-gray-500">{{ $inv->patient->k360_uid }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $inv->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $inv->category }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $inv->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : ($inv->status === 'CANCELLED' ? 'bg-gray-200 text-gray-600' : ($inv->status === 'IN_PROGRESS' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')) }}">
                                {{ $inv->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $inv->results_count }}</td>
                        <td class="px-4 py-3 text-sm text-right space-x-2" wire:click.stop>
                            @can(\App\Services\Auth\Permissions::CONSULTATIONS_EDIT)
                                @if ($inv->status === 'REQUESTED')
                                    <button wire:click="start({{ $inv->id }})" class="text-blue-700 hover:underline text-xs font-medium">Start</button>
                                @endif
                                @if (in_array($inv->status, ['REQUESTED', 'IN_PROGRESS'], true))
                                    <button wire:click="complete({{ $inv->id }})" class="text-green-700 hover:underline text-xs font-medium">Complete</button>
                                    <button wire:click="cancel({{ $inv->id }})" wire:confirm="Cancel this investigation?" class="text-red-600 hover:underline text-xs font-medium">Cancel</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @if ($detailId === $inv->id && $detail !== null)
                        <tr>
                            <td colspan="7" class="px-4 py-4 bg-gray-50">
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Structured results</h3>
                                        <table class="min-w-full text-sm mb-3">
                                            <thead>
                                                <tr class="text-left text-xs text-gray-500">
                                                    <th class="pr-3 py-1">Parameter</th>
                                                    <th class="pr-3 py-1">Value</th>
                                                    <th class="pr-3 py-1">Reference</th>
                                                    <th class="py-1">Flag</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($detail->results as $result)
                                                    <tr class="border-t border-gray-200">
                                                        <td class="pr-3 py-1">{{ $result->parameter }}</td>
                                                        <td class="pr-3 py-1">{{ $result->value }} {{ $result->unit }}</td>
                                                        <td class="pr-3 py-1 text-gray-500">{{ $result->reference_range ?? '—' }}</td>
                                                        <td class="py-1">
                                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                                                {{ $result->flag === 'NORMAL' ? 'bg-green-100 text-green-800' : ($result->flag === 'CRITICAL' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                                                {{ $result->flag }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="py-2 text-gray-500 text-xs">No results recorded yet.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                        @can(\App\Services\Auth\Permissions::CONSULTATIONS_EDIT)
                                            <form wire:submit="addResult({{ $inv->id }})" class="grid grid-cols-5 gap-2 items-end">
                                                <input wire:model="resultParameter" type="text" placeholder="Parameter *" class="rounded border-gray-300 shadow-sm text-sm">
                                                <input wire:model="resultValue" type="text" placeholder="Value *" class="rounded border-gray-300 shadow-sm text-sm">
                                                <input wire:model="resultUnit" type="text" placeholder="Unit" class="rounded border-gray-300 shadow-sm text-sm">
                                                <input wire:model="resultRange" type="text" placeholder="Reference range" class="rounded border-gray-300 shadow-sm text-sm">
                                                <div class="flex gap-1">
                                                    <select wire:model="resultFlag" class="rounded border-gray-300 shadow-sm text-sm">
                                                        <option value="NORMAL">Normal</option>
                                                        <option value="HIGH">High</option>
                                                        <option value="LOW">Low</option>
                                                        <option value="CRITICAL">Critical</option>
                                                    </select>
                                                    <button type="submit" class="px-2 py-1.5 bg-brand-600 text-white rounded text-xs">Add</button>
                                                </div>
                                                @error('resultParameter') <span class="text-red-500 text-xs col-span-2">{{ $message }}</span> @enderror
                                                @error('resultValue') <span class="text-red-500 text-xs col-span-2">{{ $message }}</span> @enderror
                                            </form>
                                        @endcan
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Reports &amp; OCR</h3>
                                        <ul class="space-y-2 mb-3">
                                            @forelse ($detailDocuments as $doc)
                                                <li class="border border-gray-200 rounded p-2 bg-white">
                                                    <div class="flex items-center justify-between">
                                                        <a href="{{ route('documents.show', $doc->id) }}" class="text-brand-700 hover:underline text-sm">{{ $doc->name }}</a>
                                                        @if ($ocrConfigured)
                                                            <button wire:click="runOcr({{ $doc->id }})" class="text-xs text-brand-700 hover:underline">Run OCR</button>
                                                        @endif
                                                    </div>
                                                    @if (! empty($doc->metadata['ocr_text']))
                                                        <p class="mt-1 text-xs text-gray-600 whitespace-pre-wrap max-h-24 overflow-y-auto">{{ \Illuminate\Support\Str::limit($doc->metadata['ocr_text'], 600) }}</p>
                                                    @endif
                                                </li>
                                            @empty
                                                <li class="text-xs text-gray-500">No reports uploaded yet.</li>
                                            @endforelse
                                        </ul>
                                        @can(\App\Services\Auth\Permissions::DOCUMENTS_UPLOAD)
                                            <form wire:submit="uploadReport({{ $inv->id }})" class="flex items-center gap-2">
                                                <input wire:model="reportFile" type="file" class="text-xs">
                                                <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white rounded text-xs">Upload</button>
                                                <span wire:loading wire:target="reportFile" class="text-xs text-gray-500">Uploading…</span>
                                            </form>
                                            @error('reportFile') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        @endcan
                                    </div>
                                </div>
                                @can(\App\Services\Auth\Permissions::AI_USE)
                                    <div class="mt-4">
                                        <livewire:ai.summary-panel
                                            context-type="investigation"
                                            :context-id="$inv->id"
                                            :features="['lab_summary' => 'AI lab summary']"
                                            wire:key="ai-summary-inv-{{ $inv->id }}" />
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No investigations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
