<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Generates concurrency-safe sequential human-readable numbers of the
 * form `<PREFIX>-<6-digit>` (e.g. K360-INV-000123).
 *
 * The previous implementation used `Model::max('id') + 1`, which races
 * under concurrent inserts: two requests can read the same max(id) and
 * both produce the same number. This helper derives the next sequence
 * value from `MAX(<numberColumn>)` while holding a transaction-level
 * write lock, and retries on collision.
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
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $next = DB::transaction(function () use ($table, $numberColumn, $prefix) {
                // Hold a write lock over the matching rows so concurrent
                // generators serialize on the same prefix range.
                $rows = DB::table($table)
                    ->where($numberColumn, 'like', $prefix.'-%')
                    ->lockForUpdate()
                    ->get([$numberColumn]);

                $max = 0;
                foreach ($rows as $row) {
                    $value = $row->{$numberColumn} ?? '';
                    // Strip the prefix and any leading zeros to recover the
                    // integer segment.
                    $tail = substr((string) $value, strlen($prefix) + 1);
                    if (is_numeric($tail) && ctype_digit((string) $tail)) {
                        $max = max($max, (int) $tail);
                    }
                }

                return $max + 1;
            });

            $number = $prefix.'-'.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);

            if (! DB::table($table)->where($numberColumn, $number)->exists()) {
                return $number;
            }
        }

        // Last-resort fallback: a high-resolution unique suffix so the value
        // remains unique even if the counter raced beyond retries.
        return $prefix.'-'.strtoupper(dechex(time())).strtoupper(dechex(random_int(0, 0xFFFFFF)));
    }
}
