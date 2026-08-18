<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue a single notification delivery so provider calls (WhatsApp/SMS/email/
 * AI/payment) never block a web request. The delivery row is the unit of work:
 * it is re-resolved by id (SerializesModels-safe) and re-dispatched by the
 * RetryNotifications command up to the configured attempt cap.
 *
 * If the underlying NotificationService dispatch fails, the delivery row is
 * marked FAILED with the error; the RetryNotifications scheduler command will
 * pick it up again under the attempt cap.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted (matches the notification
     * retry cap so worker-level retries and the scheduled retryer stay in lockstep.
     */
    public int $tries = 3;

    public int $timeout = 30;

    public int $backoff = 60;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly int $deliveryId,
        public readonly ?string $eventKey = null,
        public readonly string $channel = 'in_app',
        public readonly array $variables = [],
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);

        if ($delivery === null) {
            // Delivery was purged — nothing to do.
            return;
        }

        // Already finalized — skip (idempotent; could be a duplicate queue entry).
        if (in_array($delivery->status, ['SENT'], true)) {
            return;
        }

        $notifiable = $delivery->notifiable;

        if ($notifiable === null) {
            $delivery->update(['status' => 'FAILED', 'error' => 'Notifiable model missing']);

            return;
        }

        // Re-establish tenant context for this queued run — the job executes
        // outside the request that dispatched it, so TenantContext is empty.
        // Without this, tenant-specific template overrides are skipped (the
        // global fallback still resolves, but per-tenant templates would be
        // missed and any tenant-scoped provider lookup could 404).
        if ($delivery->tenant_id !== null) {
            app(\App\Services\Tenancy\TenantContext::class)->set($delivery->tenant_id);
        }

        $delivery->increment('attempts');

        $notifications->sendOnChannel(
            $notifiable,
            $this->eventKey ?? $delivery->event_id,
            $this->channel,
            $this->variables
        );
    }

    /**
     * On worker-level failure, mark the delivery FAILED so the scheduled
     * retryer can resume it. The framework calls this after all attempts are
     * exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);

        if ($delivery !== null && $delivery->status !== 'SENT') {
            $delivery->update(['status' => 'FAILED', 'error' => $exception->getMessage()]);
        }
    }
}
