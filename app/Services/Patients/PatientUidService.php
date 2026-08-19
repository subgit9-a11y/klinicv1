<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Models\Patient;
use App\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;

/**
 * Generates the permanent Klinic 360 patient identifier, e.g. K360-P-0000012487
 * (Document 1 §5). The UID is independent of phone/email/ABHA and unique
 * across the whole system. Generation is race-safe: it relies on the
 * `patients.k360_uid` unique constraint and retries on collision. The
 * sequence-based fallback uses the shared `sequence_counters` row-locked
 * counter — never `max('id')+1`, which races under concurrent fallbacks.
 */
class PatientUidService
{
    private const PREFIX = 'K360-P-';

    /** Sequence-counter scope shared by all fallback generators. */
    private const COUNTER_SCOPE = 'patients.k360_uid:K360-P';

    /** Number of zero-padded digits after the prefix. */
    private const PAD_LENGTH = 10;

    public function generate(): string
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $uid = $this->makeUid();

            if (! Patient::withTrashed()->where('k360_uid', $uid)->exists()) {
                return $uid;
            }
        }

        // Extremely unlikely: fall back to the DB-backed unique counter.
        return $this->generateSequential();
    }

    private function makeUid(): string
    {
        $number = random_int(1, (10 ** self::PAD_LENGTH) - 1);

        return self::PREFIX.str_pad((string) $number, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Fallback allocation via the `sequence_counters` table — a dedicated
     * row locked SELECT ... FOR UPDATE, so concurrent fallbacks serialize
     * instead of both reading the same max.
     */
    private function generateSequential(): string
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $next = DB::transaction(function () {
                $counter = SequenceCounter::lockFor(self::COUNTER_SCOPE, fn () => 1);

                return $counter->consume();
            });

            $uid = self::PREFIX.str_pad((string) $next, self::PAD_LENGTH, '0', STR_PAD_LEFT);

            if (! Patient::withTrashed()->where('k360_uid', $uid)->exists()) {
                return $uid;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique K360 patient UID.');
    }

    public function isValid(string $uid): bool
    {
        return (bool) preg_match('/^'.preg_quote(self::PREFIX, '/').'\d{'.self::PAD_LENGTH.'}$/', $uid);
    }
}
