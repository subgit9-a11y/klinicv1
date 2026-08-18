<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Jobs\SendNotificationJob;
use App\Services\Notifications\NotificationService;

/**
 * When a payment is recorded against an invoice, queue a payment-receipt
 * notification to the patient.
 */
class SendPaymentReceiptNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PaymentRecorded $event): void
    {
        $patient = $event->invoice->patient;

        if ($patient === null) {
            return;
        }

        $delivery = $this->notifications->sendOnChannel(
            $patient,
            'payment.received',
            'in_app',
            $event->variables()
        );

        if (in_array($delivery->status, ['PENDING', 'FAILED'], true)) {
            SendNotificationJob::dispatch($delivery->id, 'payment.received', 'in_app', $event->variables());
        }
    }
}
