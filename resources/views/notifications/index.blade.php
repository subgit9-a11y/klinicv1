<x-layouts.app sidebar :title="__('klinic360.nav.notifications')">
    <x-ui.page-header :title="__('klinic360.nav.notifications')" />

    @if($unreadCount > 0)
        <div class="mb-4 rounded-md bg-brand-50 border border-brand-200 px-4 py-3 text-sm text-brand-800">
            You have {{ $unreadCount }} unread notification(s).
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
        @forelse($deliveries as $delivery)
            <div class="flex items-start justify-between px-4 py-3 {{ is_null($delivery->read_at) ? 'bg-brand-50/30' : '' }}">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900">{{ $delivery->event_id ?? 'Notification' }}</div>
                    @if($delivery->recipient)
                        <div class="text-xs text-gray-500">To: {{ $delivery->recipient }}</div>
                    @endif
                    <div class="text-xs text-gray-400 mt-1">{{ $delivery->created_at?->format('d M Y, H:i') }}</div>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <span class="px-2 py-1 rounded text-xs font-medium {{ $delivery->status === 'DELIVERED' || $delivery->status === 'READ' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $delivery->status }}</span>
                    @if(is_null($delivery->read_at))
                        <form method="POST" action="{{ route('notifications.read', $delivery) }}">
                            @csrf
                            <button class="text-xs font-medium text-brand-600 hover:text-brand-800">Mark read</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-sm text-gray-500">No notifications.</div>
        @endforelse
    </div>
    {{ $deliveries->links() }}
</x-layouts.app>
