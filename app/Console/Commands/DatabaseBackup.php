<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes a database backup dump. Uses the native `mysqldump` binary when the
 * connection is MySQL/MariaDB; aborts loudly for other drivers so a silent
 * no-op backup can never be mistaken for success. Schedule it daily via cron.
 */
class DatabaseBackup extends Command
{
    protected $signature = 'klinic:backup-database {--path= : Custom output path (default storage/app/backups/db-{ts}.sql.gz)}';

    protected $description = 'Dump the production database to a compressed SQL file';

    public function handle(): int
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error("DatabaseBackup only supports mysql/mariadb (current: {$driver}).");

            return self::FAILURE;
        }

        $cfg = config("database.connections.{$connection}");
        $dir = storage_path('app/backups');
        @mkdir($dir, 0750, true);

        $path = $this->option('path')
            ?: $dir.'/db-'.now()->format('Ymd-His').'.sql.gz';

        $this->info("Backing up {$cfg['database']} to {$path}");

        $command = sprintf(
            'mysqldump --single-transaction --routines --triggers --host=%s --port=%s --user=%s --password=%s %s | gzip > %s',
            escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($cfg['port'] ?? '3306')),
            escapeshellarg((string) ($cfg['username'] ?? '')),
            escapeshellarg((string) ($cfg['password'] ?? '')),
            escapeshellarg((string) ($cfg['database'] ?? '')),
            escapeshellarg($path)
        );

        exec($command, $output, $exit);

        if ($exit !== 0 || ! file_exists($path) || filesize($path) < 1024) {
            $this->error('Backup failed (mysqldump exit '.$exit.', file '.(file_exists($path) ? filesize($path).' bytes' : 'missing').').');

            return self::FAILURE;
        }

        $this->info('Backup complete ('.number_format(filesize($path) / 1024, 1).' KB).');

        // Prune backups older than 30 days.
        $cutoff = now()->subDays(30)->timestamp;
        foreach (glob($dir.'/db-*.sql.gz') as $old) {
            if (filemtime($old) < $cutoff) {
                @unlink($old);
            }
        }

        return self::SUCCESS;
    }
}
