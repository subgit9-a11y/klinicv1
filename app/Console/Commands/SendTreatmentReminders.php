<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TreatmentBooking;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendTreatmentReminders extends Command
{
    protected $signature = 'klinic:send-treatment-reminders';
    protected $description = 'Send reminders for treatment sessions scheduled within the next 24 hours';

    public function handle(NotificationService $notifications): int
    {
        $windowStart = Carbon::now()->toDateString();
        $windowEnd = Carbon::now()->addDay()->toDateString();

        $bookings = TreatmentBooking::query()
            ->where('status', 'BOOKED')
            ->whereBetween('booking_date', [$windowStart, $windowEnd])
            ->with('patient')
            ->limit(500)
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $notifications->send(
                $booking->patient,
                'treatment.reminder',
                ['booking_date' => $booking->booking_date->format('Y-m-d'), 'start_time' => $booking->start_time],
                ['in_app']
            );
            $sent++;
        }

        $this->info("Sent {$sent} treatment reminders.");
        return self::SUCCESS;
    }
}
