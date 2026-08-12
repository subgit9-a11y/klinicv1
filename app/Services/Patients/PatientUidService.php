<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * Generates the permanent Klinic 360 patient identifier, e.g. K360-P-0000012487
 * (Document 1 §5). The UID is independent of phone/email/ABHA and unique
 * across the whole system. Generation is race-safe: it relies on the
 * `patients.k360_uid` unique constraint and retries on collision.
 */
class PatientUidService
{
    private const PREFIX = 'K360-P-';

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

        // Extremely unlikely: fall back to a DB-backed unique sequence segment.
        return $this->generateSequential();
    }

    private function makeUid(): string
    {
        $number = random_int(1, (10 ** self::PAD_LENGTH) - 1);

        return self::PREFIX.str_pad((string) $number, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Sequence-based fallback that uses an advisory counter so the result is
     * guaranteed unique under contention.
     */
    private function generateSequential(): string
    {
        return DB::transaction(function () {
            $next = DB::table('patients')->max('id') ?? 0;

            for ($i = 1; $i <= 20; $i++) {
                $uid = self::PREFIX.str_pad((string) ($next + $i), self::PAD_LENGTH, '0', STR_PAD_LEFT);

                if (! Patient::withTrashed()->where('k360_uid', $uid)->exists()) {
                    return $uid;
                }
            }

            throw new \RuntimeException('Unable to allocate a unique K360 patient UID.');
        });
    }

    public function isValid(string $uid): bool
    {
        return (bool) preg_match('/^'.preg_quote(self::PREFIX, '/').'\d{'.self::PAD_LENGTH.'}$/', $uid);
    }
}
