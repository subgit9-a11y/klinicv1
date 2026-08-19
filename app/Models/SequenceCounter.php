<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Generic row-locked sequence counter (invoice/payment/refund/IPD numbers,
 * patient UID fallback). Deliberately NOT tenant-scoped: number formats are
 * globally unique, so counters are system-level by design.
 *
 * Creation races resolve via the unique index on `scope`: the loser re-reads
 * the winner's row. Callers must run inside a transaction: lockFor() takes
 * SELECT ... FOR UPDATE on the counter row before it is consumed.
 */
class SequenceCounter extends Model
{
    protected $fillable = ['scope', 'next_value'];

    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }

    /**
     * Fetch (creating on first use) the counter row for $scope and lock it
     * for update. $initializer computes the first next_value from existing
     * data (deployment continuity) — invoked inside the create attempt.
     */
    public static function lockFor(string $scope, callable $initializer): self
    {
        $row = self::where('scope', $scope)->lockForUpdate()->first();

        if ($row !== null) {
            return $row;
        }

        try {
            return self::create(['scope' => $scope, 'next_value' => max(1, (int) $initializer())]);
        } catch (UniqueConstraintViolationException) {
            return self::where('scope', $scope)->lockForUpdate()->firstOrFail();
        }
    }

    /**
     * Consume the next value from this (locked) counter and return it.
     */
    public function consume(): int
    {
        $value = (int) $this->next_value;
        $this->increment('next_value');

        return $value;
    }
}
