<div class="border border-brand-200 rounded-lg p-4 bg-brand-50">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900">AI summaries</h3>
        <div class="flex gap-2">
            @foreach ($features as $key => $label)
                <button wire:click="generate('{{ $key }}')" wire:loading.attr="disabled"
                        class="px-2 py-1 bg-white border border-brand-300 text-brand-700 rounded text-xs font-medium hover:bg-brand-100">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if (session('message'))
        <div class="mb-2 p-2 bg-white text-gray-700 rounded text-xs border border-brand-200">{{ session('message') }}</div>
    @endif

    <div wire:loading class="text-xs text-gray-500 mb-2">Generating AI draft…</div>

    @forelse ($requests as $request)
        <div class="bg-white border border-gray-200 rounded p-3 mb-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-700">{{ $request->feature?->name ?? $request->feature?->key ?? 'AI' }}</span>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $request->output_status === 'APPROVED' ? 'bg-green-100 text-green-800' : ($request->output_status === 'DRAFT' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                        {{ $request->output_status }}
                    </span>
                    @if ($request->output_status === 'DRAFT')
                        <button wire:click="approve({{ $request->id }})" class="text-green-700 hover:underline text-xs font-medium">Approve</button>
                        <button wire:click="reject({{ $request->id }})" class="text-red-600 hover:underline text-xs font-medium">Reject</button>
                    @endif
                </div>
            </div>
            @if ($request->output)
                <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ $request->output }}</p>
            @elseif ($request->error)
                <p class="mt-2 text-xs text-red-600">{{ $request->error }}</p>
            @endif
            <div class="mt-1 text-xs text-gray-400">{{ $request->created_at?->format('d M Y, H:i') }}</div>
        </div>
    @empty
        <p class="text-xs text-gray-500">No AI summaries yet — generate one above.</p>
    @endforelse
</div>
