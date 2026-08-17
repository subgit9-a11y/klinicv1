<x-layouts.app sidebar :title="__('klinic360.nav.documents')">
    <x-ui.page-header :title="__('klinic360.nav.documents')" />

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Uploaded</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($documents as $doc)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $doc->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $doc->type }} · {{ $doc->mime_type }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ number_format($doc->size / 1024, 1) }} KB</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $doc->created_at?->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('documents.show', $doc) }}" class="text-xs font-medium text-brand-600 hover:text-brand-800">Download</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No documents uploaded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $documents->links() }}
</x-layouts.app>
