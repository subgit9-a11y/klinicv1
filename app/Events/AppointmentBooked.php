<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an appointment is created (booked). Listeners dispatch
 * notification jobs (confirmation to the patient) asynchronously so the
 * web request is never blocked by WhatsApp/SMS/email provider calls.
 */
class AppointmentBooked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly ?array $variables = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        $appt = $this->appointment;
        $date = $appt->appointment_date instanceof \Illuminate\Support\Carbon
            ? $appt->appointment_date->format('Y-m-d')
            : (string) $appt->appointment_date;

        return $this->variables ?? [
            'patient_name' => $appt->patient?->name ?? '',
            'doctor_name' => $appt->doctor?->name ?? '',
            'appointment_date' => $date,
            'start_time' => $appt->start_time,
            'reference' => (string) $appt->id,
        ];
    }
}
