@props(['columns' => [], 'rows' => [], 'empty' => null])

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            @if(!empty($columns))
                <thead class="bg-gray-50">
                    <tr>
                        @foreach($columns as $col)
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ is_array($col) ? ($col['label'] ?? '') : $col }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        @foreach($row as $cell)
                            <td class="px-4 py-3 text-sm text-gray-700">
                                @if(is_string($cell) || is_numeric($cell))
                                    {{ $cell }}
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) ?: 1 }}" class="px-4 py-8 text-center text-sm text-gray-400">
                            {{ $empty ?? __('klinic360.no_records') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
