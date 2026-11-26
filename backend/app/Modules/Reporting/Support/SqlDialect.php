<?php

namespace App\Modules\Reporting\Support;

use Illuminate\Support\Facades\DB;

/**
 * The few driver-specific SQL fragments of the reports (MySQL in every environment,
 * SQLite on the CI portability leg).
 */
final class SqlDialect
{
    private static function sqlite(): bool
    {
        return DB::connection(config('tenancy.database.central_connection'))->getDriverName() === 'sqlite';
    }

    /**
     * Fractional days between two timestamp columns.
     */
    public static function daysBetween(string $from, string $to): string
    {
        return self::sqlite()
            ? "(julianday({$to}) - julianday({$from}))"
            : "(TIMESTAMPDIFF(SECOND, {$from}, {$to}) / 86400)";
    }

    /**
     * `YYYY-MM` of a timestamp column.
     */
    public static function month(string $column): string
    {
        return self::sqlite() ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
