<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Internal per-doctor per-day queue-token counter. Never exposed via API —
 * rows are always queried with an explicit (tenant_id, user_id, token_date)
 * key, so this intentionally skips the BelongsToTenant global scope (the
 * counter must resolve from the appointment's tenant, not request context).
 */
class TokenSequence extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'token_date', 'next_number'];

    protected function casts(): array
    {
        return [
            'token_date' => 'date',
            'next_number' => 'integer',
        ];
    }

    /**
     * Fetch (creating on first use) the counter row for a doctor/day and
     * lock it for update. Callers must run inside a transaction.
     *
     * Creation races resolve via the unique index: the loser re-reads the
     * winner's row. New counters initialize from the max token number
     * already issued (deployment continuity for pre-counter tokens).
     */
    public static function lockFor(int $tenantId, int $userId, string $date): self
    {
        // whereDate (not where): SQLite stores date columns as 'Y-m-d H:i:s'
        // text, so a plain string comparison never matches.
        $row = self::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('token_date', $date)
            ->lockForUpdate()
            ->first();

        if ($row !== null) {
            return $row;
        }

        try {
            // CAST guards against lexicographic ordering of the zero-padded
            // string column ('1000' < '999' as text); valid on SQLite+MySQL.
            // whereDate on appointment_date handles SQLite storing date
            // columns as 'Y-m-d H:i:s' text.
            $initial = (int) AppointmentToken::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereDate('appointment_date', $date)
                ->max(\Illuminate\Support\Facades\DB::raw('CAST(token_number AS SIGNED)'));

            return self::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'token_date' => $date,
                'next_number' => $initial + 1,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return self::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereDate('token_date', $date)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    /**
     * Consume the next token number from this (locked) counter.
     */
    public function consume(): string
    {
        $number = str_pad((string) $this->next_number, 3, '0', STR_PAD_LEFT);
        $this->increment('next_number');

        return $number;
    }
}
