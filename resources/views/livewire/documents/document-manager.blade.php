<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Documents</h1>
            <p class="text-sm text-gray-600">Upload, categorize, version, OCR, share, archive and delete clinic documents.</p>
        </div>
        @can(\App\Services\Auth\Permissions::DOCUMENTS_UPLOAD)
            <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                {{ $showForm ? 'Close' : '+ Upload document' }}
            </button>
        @endcan
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Upload document</h2>
            <form wire:submit="upload" class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                    <input wire:model="file" type="file" class="w-full text-sm">
                    <span wire:loading wire:target="file" class="text-xs text-gray-500">Uploading…</span>
                    @error('file') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select wire:model="type" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach ($types as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient (optional)</label>
                    <input wire:model.live.debounce.300ms="patientSearch" type="text" placeholder="Search by name or UID…" class="w-full rounded border-gray-300 shadow-sm">
                    @if ($patients->isNotEmpty())
                        <select wire:model="patient_id" class="w-full mt-1 rounded border-gray-300 shadow-sm">
                            <option value="">No patient</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->k360_uid }})</option>
                            @endforeach
                        </select>
                    @endif
                    @error('patient_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-3">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Upload</button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2 items-center">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name or OCR text…" class="rounded border-gray-300 shadow-sm text-sm w-64">
        <select wire:model.live="typeFilter" class="rounded border-gray-300 shadow-sm text-sm">
            <option value="">All types</option>
            @foreach ($types as $t)
                <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-1 text-sm text-gray-600">
            <input wire:model.live="showArchived" type="checkbox" class="rounded border-gray-300">
            Show archived
        </label>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($items as $doc)
                    @php $isArchived = ! empty($doc->metadata['archived_at']); @endphp
                    <tr class="cursor-pointer hover:bg-gray-50 {{ $isArchived ? 'opacity-60' : '' }}" wire:click="openDetail({{ $doc->id }})">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            {{ $doc->name }}
                            @if ($isArchived)
                                <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600">ARCHIVED</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            @if ($doc->patient)
                                {{ $doc->patient->first_name }} {{ $doc->patient->last_name }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm" wire:click.stop>
                            @can(\App\Services\Auth\Permissions::DOCUMENTS_UPLOAD)
                                <select wire:change="recategorize({{ $doc->id }}, $event.target.value)" class="rounded border-gray-300 text-xs py-1">
                                    @foreach ($types as $t)
                                        <option value="{{ $t }}" @selected($doc->type === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="text-gray-600">{{ $doc->type }}</span>
                            @endcan
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">v{{ $doc->metadata['version'] ?? 1 }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $doc->created_at?->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-sm text-right space-x-2" wire:click.stop>
                            <a href="{{ route('documents.show', $doc) }}" class="text-brand-700 hover:underline text-xs font-medium">Download</a>
                            <button wire:click="share({{ $doc->id }})" class="text-brand-700 hover:underline text-xs font-medium">Share</button>
                            @if ($ocrConfigured)
                                <button wire:click="runOcr({{ $doc->id }})" class="text-blue-700 hover:underline text-xs font-medium">OCR</button>
                            @endif
                            @can(\App\Services\Auth\Permissions::DOCUMENTS_UPLOAD)
                                @if ($isArchived)
                                    <button wire:click="unarchive({{ $doc->id }})" class="text-gray-700 hover:underline text-xs font-medium">Restore</button>
                                @else
                                    <button wire:click="archive({{ $doc->id }})" class="text-gray-700 hover:underline text-xs font-medium">Archive</button>
                                @endif
                            @endcan
                            @can(\App\Services\Auth\Permissions::DOCUMENTS_DELETE)
                                <button wire:click="delete({{ $doc->id }})" wire:confirm="Permanently delete this document?" class="text-red-600 hover:underline text-xs font-medium">Delete</button>
                            @endcan
                        </td>
                    </tr>
                    @if ($detailId === $doc->id && $detail !== null)
                        <tr>
                            <td colspan="6" class="px-4 py-4 bg-gray-50">
                                <div class="grid grid-cols-3 gap-6">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Versions</h3>
                                        <ul class="space-y-1 mb-3">
                                            @foreach ($versions as $version)
                                                <li class="text-xs {{ $version->id === $detail->id ? 'font-semibold text-brand-700' : 'text-gray-600' }}">
                                                    v{{ $version->metadata['version'] ?? 1 }} — {{ $version->name }}
                                                    <span class="text-gray-400">({{ $version->created_at?->format('d M Y') }})</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @can(\App\Services\Auth\Permissions::DOCUMENTS_UPLOAD)
                                            <form wire:submit="uploadNewVersion({{ $doc->id }})" class="flex items-center gap-2">
                                                <input wire:model="versionFile" type="file" class="text-xs w-40">
                                                <button type="submit" class="px-2 py-1 bg-brand-600 text-white rounded text-xs">New version</button>
                                            </form>
                                            @error('versionFile') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        @endcan
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Extracted text (OCR)</h3>
                                        @if (! empty($detail->metadata['ocr_text']))
                                            <p class="text-xs text-gray-600 whitespace-pre-wrap max-h-40 overflow-y-auto">{{ \Illuminate\Support\Str::limit($detail->metadata['ocr_text'], 1200) }}</p>
                                        @else
                                            <p class="text-xs text-gray-500">No OCR text extracted yet.</p>
                                        @endif
                                        @if ($shareDocId === $detail->id && $shareUrl !== null)
                                            <div class="mt-3">
                                                <h4 class="text-xs font-semibold text-gray-900 mb-1">Share link (expires in 24h)</h4>
                                                <input type="text" readonly value="{{ $shareUrl }}" class="w-full text-xs rounded border-gray-300 bg-white" onclick="this.select()">
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Access audit</h3>
                                        <ul class="space-y-1">
                                            @forelse ($auditTrail as $entry)
                                                <li class="text-xs text-gray-600">
                                                    <span class="font-medium">{{ $entry->action }}</span>
                                                    <span class="text-gray-400">{{ $entry->created_at?->format('d M Y H:i') }}</span>
                                                </li>
                                            @empty
                                                <li class="text-xs text-gray-500">No audit events for this document.</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                </div>
                                @can(\App\Services\Auth\Permissions::AI_USE)
                                    <div class="mt-4">
                                        <livewire:ai.summary-panel
                                            context-type="document"
                                            :context-id="$doc->id"
                                            :features="['document_summary' => 'AI document summary']"
                                            wire:key="ai-summary-doc-{{ $doc->id }}" />
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No documents found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
