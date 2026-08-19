<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;

/**
 * Generates concurrency-safe sequential human-readable numbers of the
 * form `<PREFIX>-<6-digit>` (e.g. K360-INV-000123).
 *
 * Backed by a dedicated `sequence_counters` row locked with
 * SELECT ... FOR UPDATE — the previous implementation locked the LIKE
 * range over the target table, which serializes on nothing when zero
 * matching rows exist (both concurrent generators would read max=0). The
 * counter initializes from the target table's existing numeric-tail max
 * on first use (deployment continuity). The unique constraint on the
 * number column remains the final arbiter; the existence-check loop is a
 * cheap backstop for legacy collisions.
 */
final class SequentialNumber
{
    /**
     * @param  string  $table  Target table holding the number column.
     * @param  string  $prefix  e.g. 'K360-INV'.
     * @param  string  $numberColumn  Column storing the human-readable number.
     * @param  int  $pad  Zero-pad length of the numeric segment.
     */
    public static function next(string $table, string $prefix, string $numberColumn, int $pad = 6): string
    {
        $scope = "{$table}.{$numberColumn}:{$prefix}";

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $next = DB::transaction(function () use ($table, $numberColumn, $prefix, $scope) {
                $counter = SequenceCounter::lockFor($scope, fn () => self::initialValue($table, $numberColumn, $prefix) + 1);

                return $counter->consume();
            });

            $number = $prefix.'-'.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);

            if (! DB::table($table)->where($numberColumn, $number)->exists()) {
                return $number;
            }
        }

        // Last-resort fallback: a high-resolution unique suffix so the value
        // remains unique even if the counter drifted past retries.
        return $prefix.'-'.strtoupper(dechex(time())).strtoupper(dechex(random_int(0, 0xFFFFFF)));
    }

    /**
     * Highest numeric tail among existing `<PREFIX>-<digits>` values.
     * Extraction is done in PHP so it is portable across SQLite and MySQL.
     */
    private static function initialValue(string $table, string $numberColumn, string $prefix): int
    {
        $max = 0;
        DB::table($table)
            ->where($numberColumn, 'like', $prefix.'-%')
            ->pluck($numberColumn)
            ->each(function ($value) use (&$max, $prefix) {
                $tail = substr((string) $value, strlen($prefix) + 1);
                if (is_numeric($tail) && ctype_digit((string) $tail)) {
                    $max = max($max, (int) $tail);
                }
            });

        return $max;
    }
}
