<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Sql
{
    /**
     * Driver-aware SQL expression concatenating first_name + ' ' + last_name.
     * SQLite uses ||, MySQL/MariaDB use CONCAT with COALESCE for NULL last names.
     */
    public static function personNameConcat(?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? 'first_name || " " || last_name'
            : 'CONCAT(first_name, " ", COALESCE(last_name, ""))';
    }
}
