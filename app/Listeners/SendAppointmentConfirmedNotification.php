<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AppointmentConfirmed;
use App\Jobs\SendNotificationJob;
use App\Services\Notifications\NotificationService;

/**
 * When an appointment is confirmed (e.g. payment verified for an online
 * booking), queue a confirmation notification to the patient.
 */
class SendAppointmentConfirmedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(AppointmentConfirmed $event): void
    {
        $appointment = $event->appointment;
        $patient = $appointment->patient;

        if ($patient === null) {
            return;
        }

        $delivery = $this->notifications->sendOnChannel(
            $patient,
            'appointment.confirmation',
            'in_app',
            $event->variables()
        );

        if (in_array($delivery->status, ['PENDING', 'FAILED'], true)) {
            SendNotificationJob::dispatch($delivery->id, 'appointment.confirmation', 'in_app', $event->variables());
        }
    }
}
