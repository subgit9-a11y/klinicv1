<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = NotificationDelivery::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->id)
            ->where('channel', 'in_app')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $deliveries = $query->paginate(25);
        $unreadCount = (clone $query)->whereNull('read_at')->count();

        return view('notifications.index', [
            'deliveries' => $deliveries,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, NotificationDelivery $delivery)
    {
        $this->authorizeNotification($delivery, $request->user()->id);
        $delivery->update(['read_at' => now(), 'status' => 'READ']);

        return redirect()->route('notifications.index');
    }

    private function authorizeNotification(NotificationDelivery $delivery, int $userId): void
    {
        if ($delivery->notifiable_id !== $userId) {
            abort(403);
        }
    }
}
