<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationService;
use App\Services\Tenancy\TenantContext;

/**
 * When an appointment is booked, prepare a PENDING in-app confirmation delivery
 * for the patient and queue a job to send it. Keeping the delivery record
 * creation in the listener (not the service) means the booking transaction
 * commits before any provider call is attempted.
 */
class SendAppointmentBookedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(AppointmentBooked $event): void
    {
        $appointment = $event->appointment;
        $patient = $appointment->patient;

        if ($patient === null) {
            return;
        }

        // Preserve tenant context for the queued job (it runs outside the
        // request that dispatched it).
        $tenantContext = app(TenantContext::class);
        $tenantId = $tenantContext->isSet() ? $tenantContext->id() : $appointment->tenant_id;

        $delivery = $this->notifications->sendOnChannel(
            $patient,
            'appointment.confirmation',
            'in_app',
            $event->variables()
        );

        if (in_array($delivery->status, ['PENDING', 'FAILED'], true) && $tenantId !== null) {
            SendNotificationJob::dispatch($delivery->id, 'appointment.confirmation', 'in_app', $event->variables());
        }
    }
}
