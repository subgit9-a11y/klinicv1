<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

class RetryNotifications extends Command
{
    private const MAX_ATTEMPTS = 3;

    protected $signature = 'klinic:retry-notifications';
    protected $description = 'Retry pending and failed notification deliveries under the attempt cap';

    public function handle(NotificationService $notifications): int
    {
        $deliveries = NotificationDelivery::query()
            ->whereIn('status', ['PENDING', 'FAILED'])
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->limit(200)
            ->get();

        $retried = 0;
        foreach ($deliveries as $delivery) {
            $notifiable = $delivery->notifiable;
            if (!$notifiable) {
                continue;
            }

            $delivery->increment('attempts');

            try {
                $notifications->sendOnChannel(
                    $notifiable,
                    $delivery->event_id,
                    $delivery->channel,
                    []
                );
                $retried++;
            } catch (\Throwable $e) {
                $delivery->update(['status' => 'FAILED', 'error' => $e->getMessage()]);
            }
        }

        $this->info("Retried {$retried} notification deliveries.");
        return self::SUCCESS;
    }
}
