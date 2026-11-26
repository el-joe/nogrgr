<?php

namespace App\Modules\Reporting\Support;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ReportBucket;
use App\Shared\Money\Money;
use App\Shared\Support\Locale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

/**
 * Display formatting of report values (USD minor units, counts, percentages, buckets).
 * CSV exports use the raw values instead.
 */
final class ReportFormat
{
    public static function money(int $minor): string
    {
        return Money::ofMinor($minor)->format();
    }

    public static function number(int|float $value, int $precision = 0): string
    {
        return (string) Number::format($value, $precision, locale: Locale::numberLocale());
    }

    public static function percent(float $value): string
    {
        return Number::format($value, maxPrecision: 1, locale: Locale::numberLocale()).'%';
    }

    /**
     * Axis labels of the window's buckets ("3 Nov" or "Nov 2026").
     *
     * @return list<string>
     */
    public static function bucketLabels(ReportFilterData $filters): array
    {
        $locale = app()->getLocale();
        $month = $filters->bucket() === ReportBucket::Month;

        return array_map(
            static fn (string $key): string => $month
                ? CarbonImmutable::parse($key.'-01')->locale($locale)->isoFormat('MMM YYYY')
                : CarbonImmutable::parse($key)->locale($locale)->isoFormat('D MMM'),
            $filters->bucketKeys(),
        );
    }

    public static function month(string $key): string
    {
        return CarbonImmutable::parse(substr($key, 0, 7).'-01')->locale(app()->getLocale())->isoFormat('MMMM YYYY');
    }

    public static function period(ReportFilterData $filters): string
    {
        $locale = app()->getLocale();

        return $filters->start()->locale($locale)->isoFormat('D MMM').' – '.$filters->end()->locale($locale)->isoFormat('D MMM YYYY');
    }
}
