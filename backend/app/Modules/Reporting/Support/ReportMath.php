<?php

namespace App\Modules\Reporting\Support;

use App\Modules\Reporting\DTOs\ReportFilterData;

/**
 * Pure aggregation helpers of the reports (unit tested): ratios, period deltas and
 * bucketing of daily rows into the chart series of a window.
 */
final class ReportMath
{
    /**
     * Share in percent (0 when the base is 0), rounded to one decimal.
     */
    public static function percent(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    /**
     * Change vs the previous period in percent; null when there is no base to compare.
     */
    public static function delta(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    /**
     * Integer average (minor units), 0 when there is nothing to average.
     */
    public static function average(int $sum, int $count): int
    {
        return $count > 0 ? (int) round($sum / $count) : 0;
    }

    /**
     * Sums daily values (keyed `Y-m-d`) into the window's buckets, zero-filled.
     *
     * @param  array<string, int|float>  $daily
     * @return list<int|float>
     */
    public static function series(ReportFilterData $filters, array $daily): array
    {
        $bucket = $filters->bucket();
        $values = array_fill_keys($filters->bucketKeys(), 0);

        foreach ($daily as $day => $value) {
            $key = $bucket->keyOf((string) $day);

            if (array_key_exists($key, $values)) {
                $values[$key] += $value;
            }
        }

        return array_values($values);
    }

    /**
     * Keeps the largest `$limit - 1` slices and folds the rest into one "others" slice.
     *
     * @param  array<string, int|float>  $slices  label => value
     * @return array<string, int|float>
     */
    public static function topSlices(array $slices, int $limit, string $othersLabel): array
    {
        arsort($slices);

        if (count($slices) <= $limit) {
            return $slices;
        }

        $top = array_slice($slices, 0, $limit - 1, true);
        $top[$othersLabel] = array_sum(array_slice($slices, $limit - 1, null, true));

        return $top;
    }
}
