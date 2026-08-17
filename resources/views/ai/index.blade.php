<x-layouts.app sidebar :title="__('klinic360.nav.ai')">
    <x-ui.page-header title="AI Assistant — Drafts for Practitioner Review" />

    @if(session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Generate AI draft</h3>
        <form method="POST" action="{{ route('ai.generate') }}">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Feature key</label>
                    <input type="text" name="feature_key" placeholder="e.g. opd_summary" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Patient ID</label>
                    <input type="number" name="patient_id" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Medicine system</label>
                    <input type="text" name="system" placeholder="allopathy / ayurveda" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                </div>
            </div>
            <button type="submit" class="mt-3 bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-brand-700">Generate</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Request</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Feature</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Output</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $req)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">#{{ $req->id }} · {{ $req->provider }}/{{ $req->model }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $req->feature?->key ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-md truncate">{{ $req->output ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $req->output_status === 'APPROVED' ? 'bg-green-100 text-green-800' : ($req->output_status === 'REJECTED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">{{ $req->output_status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($req->output_status === 'PENDING')
                                <form method="POST" action="{{ route('ai.approve', $req) }}" class="inline">
                                    @csrf
                                    <button class="text-xs font-medium text-green-600 hover:text-green-800">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('ai.reject', $req) }}" class="inline ml-2">
                                    @csrf
                                    <button class="text-xs font-medium text-red-600 hover:text-red-800">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No AI drafts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
</x-layouts.app>
