<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">AI Scribe</h2>
        <span class="text-xs text-gray-400">Draft only — requires doctor approval before saving</span>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <form wire:submit="scribeFromAudio" class="border border-gray-100 rounded p-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">1. Dictate → transcribe</div>
            <input wire:model="audio" type="file" accept=".mp3,.wav,.m4a,.ogg" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
            @error('audio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            <button type="submit" wire:loading.attr="disabled" class="mt-3 px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700 disabled:opacity-50">
                <span wire:loading.remove>Transcribe & draft</span>
                <span wire:loading>Transcribing…</span>
            </button>
        </form>

        <form wire:submit="scribeFromComplaint" class="border border-gray-100 rounded p-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">2. Use chief complaint text</div>
            <p class="text-xs text-gray-500 mb-2">Generates a draft from the consultation's existing chief complaint.</p>
            <button type="submit" class="mt-3 px-4 py-2 bg-gray-700 text-white rounded-md text-sm font-medium hover:bg-gray-800">Draft from complaint</button>
        </form>
    </div>

    @if ($aiRequestId)
        <div class="border-t border-gray-100 pt-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">SOAP draft — edit before approving</h3>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Subjective (history)</label>
                    <textarea wire:model="subjective" rows="3" class="w-full rounded border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Objective (examination)</label>
                    <textarea wire:model="objective" rows="3" class="w-full rounded border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Assessment (diagnosis)</label>
                    <textarea wire:model="assessment" rows="3" class="w-full rounded border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Plan</label>
                    <textarea wire:model="plan" rows="3" class="w-full rounded border-gray-300 shadow-sm text-sm"></textarea>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <button wire:click="approve" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">Approve & write to consultation</button>
                <button wire:click="discard" wire:confirm="Discard this draft?" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-md text-sm font-medium hover:bg-gray-200">Discard</button>
            </div>
            <p class="mt-2 text-xs text-gray-400">Approving writes history/examination/assessment/treatment_plan onto this consultation.</p>
        </div>
    @else
        <p class="text-sm text-gray-400">No draft yet. Record a dictation or draft from the chief complaint.</p>
    @endif
</div>
