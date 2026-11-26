<?php

namespace App\Modules\Reporting\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Chart granularity: daily up to 62 days, monthly beyond.
 */
enum ReportBucket: string
{
    use HasEnumHelpers;

    case Day = 'day';
    case Month = 'month';

    public const DAILY_LIMIT = 62;

    public static function forDays(int $days): self
    {
        return $days > self::DAILY_LIMIT ? self::Month : self::Day;
    }

    /**
     * Bucket key of a `Y-m-d` date.
     */
    public function keyOf(string $date): string
    {
        return $this === self::Month ? substr($date, 0, 7) : substr($date, 0, 10);
    }
}
