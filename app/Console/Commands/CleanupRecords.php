<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupRecords extends Command
{
    protected $signature = 'klinic:cleanup';
    protected $description = 'Purge expired API tokens and audit logs older than the retention window';

    public function handle(): int
    {
        $now = Carbon::now();

        $tokens = ApiToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->delete();

        $retentionDays = (int) config('klinic.audit_retention_days', 365);
        $auditCutoff = Carbon::now()->subDays($retentionDays);

        $audits = AuditLog::query()
            ->where('created_at', '<', $auditCutoff)
            ->delete();

        $this->info("Deleted {$tokens} expired tokens and {$audits} aged audit logs (>{$retentionDays}d).");
        return self::SUCCESS;
    }
}
