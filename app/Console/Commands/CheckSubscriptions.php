<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckSubscriptions extends Command
{
    protected $signature = 'klinic:check-subscriptions';

    protected $description = 'Expire subscriptions past their end date and mark overdue ones';

    public function handle(): int
    {
        $now = Carbon::now();

        $expired = Subscription::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update(['status' => 'EXPIRED']);

        $this->info("Expired {$expired} subscriptions.");

        return self::SUCCESS;
    }
}
