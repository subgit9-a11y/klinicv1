<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Followup;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendFollowupReminders extends Command
{
    protected $signature = 'klinic:send-followup-reminders';
    protected $description = 'Send reminders for follow-ups due today';

    public function handle(NotificationService $notifications): int
    {
        $today = Carbon::today()->toDateString();

        $followups = Followup::query()
            ->where('status', 'PENDING')
            ->where('due_date', '<=', $today)
            ->with('patient')
            ->limit(500)
            ->get();

        $sent = 0;
        foreach ($followups as $followup) {
            if (!$followup->patient) {
                continue;
            }
            $notifications->send(
                $followup->patient,
                'followup.reminder',
                ['due_date' => $followup->due_date->format('Y-m-d')],
                ['in_app']
            );
            $sent++;
        }

        $this->info("Sent {$sent} follow-up reminders.");
        return self::SUCCESS;
    }
}
