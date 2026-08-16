<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification used by NotificationService when
 * dispatching to the in_app channel.
 */
class GenericInAppNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $body
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
