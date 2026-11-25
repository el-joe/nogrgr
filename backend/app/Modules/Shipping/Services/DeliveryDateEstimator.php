<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\DTOs\DeliveryEstimate;
use App\Modules\Shipping\Support\StopPeriodRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Pure, deterministic delivery-date maths (A3 estimator, ADM-033d).
 *
 *   start      = order date (next day when the order time is at / after the cut-off)
 *   day N      = the N-th calendar day after start that is NOT a weekly off day and NOT
 *                inside an active stop period (those days are skipped, not counted)
 *   min_date   = day min_days, max_date = day max_days (0 days = the start day itself)
 *
 * Worked example (Figma 71:46296): order Thu 25 Dec 2026, KSA 1–3 days, Friday off,
 * stop period 27 Dec → Fri 26 and Sat 27 skipped → Sun 28 – Tue 30 Dec.
 */
final class DeliveryDateEstimator
{
    /** Safety limit: a calendar where (almost) every day is a stop day cannot loop forever. */
    public const MAX_SCAN_DAYS = 730;

    /**
     * @param  list<StopPeriodRule>  $stops
     * @param  list<int>  $weeklyOffDays  Carbon dayOfWeek numbers (0 = Sunday)
     * @param  string|null  $cutoff  "HH:MM"; orders at / after it start the next day
     */
    public function estimate(
        CarbonInterface $orderedAt,
        int $minDays,
        int $maxDays,
        array $stops = [],
        array $weeklyOffDays = [],
        ?string $cutoff = null,
    ): DeliveryEstimate {
        if ($minDays < 0 || $maxDays < $minDays) {
            throw new InvalidArgumentException("Invalid delivery window [{$minDays}, {$maxDays}].");
        }

        $start = $this->startDate($orderedAt, $cutoff);
        $minDate = $this->nthDeliveryDay($start, $minDays, $stops, $weeklyOffDays);
        $maxDate = $this->nthDeliveryDay($start, $maxDays, $stops, $weeklyOffDays);

        return new DeliveryEstimate(
            startDate: $start,
            minDate: $minDate,
            maxDate: $maxDate,
            minDays: $minDays,
            maxDays: $maxDays,
            skipped: $this->skippedBetween($start, $maxDate, $stops, $weeklyOffDays),
        );
    }

    /**
     * The calendar dates of delivery days 1..$count (delivery popup preview).
     *
     * @param  list<StopPeriodRule>  $stops
     * @param  list<int>  $weeklyOffDays
     * @return array<int, CarbonImmutable> day number => date
     */
    public function deliveryDays(CarbonInterface $orderedAt, int $count, array $stops = [], array $weeklyOffDays = [], ?string $cutoff = null): array
    {
        $start = $this->startDate($orderedAt, $cutoff);
        $days = [];
        $cursor = $start;

        for ($day = 1; $day <= $count; $day++) {
            $cursor = $this->nthDeliveryDay($cursor, 1, $stops, $weeklyOffDays);
            $days[$day] = $cursor;
        }

        return $days;
    }

    public function startDate(CarbonInterface $orderedAt, ?string $cutoff = null): CarbonImmutable
    {
        $moment = CarbonImmutable::instance($orderedAt);
        $start = $moment->startOfDay();

        if ($cutoff !== null && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $cutoff) === 1 && $moment->format('H:i') >= $cutoff) {
            $start = $start->addDay();
        }

        return $start;
    }

    /**
     * @param  list<StopPeriodRule>  $stops
     * @param  list<int>  $weeklyOffDays
     */
    public function isStopDay(CarbonInterface $date, array $stops, array $weeklyOffDays = []): bool
    {
        return $this->stopReason($date, $stops, $weeklyOffDays) !== false;
    }

    /**
     * @param  list<StopPeriodRule>  $stops
     * @param  list<int>  $weeklyOffDays
     */
    private function nthDeliveryDay(CarbonImmutable $start, int $n, array $stops, array $weeklyOffDays): CarbonImmutable
    {
        $date = $start;
        $counted = 0;
        $scanned = 0;

        while ($counted < $n) {
            $date = $date->addDay();

            if (++$scanned > self::MAX_SCAN_DAYS) {
                throw new InvalidArgumentException('No delivery day within '.self::MAX_SCAN_DAYS.' days: every day is a stop day.');
            }

            if (! $this->isStopDay($date, $stops, $weeklyOffDays)) {
                $counted++;
            }
        }

        return $date;
    }

    /**
     * @param  list<StopPeriodRule>  $stops
     * @param  list<int>  $weeklyOffDays
     * @return list<array{date: string, reason: string|null}>
     */
    private function skippedBetween(CarbonImmutable $start, CarbonImmutable $end, array $stops, array $weeklyOffDays): array
    {
        $skipped = [];

        for ($date = $start->addDay(); $date->lte($end); $date = $date->addDay()) {
            $reason = $this->stopReason($date, $stops, $weeklyOffDays);

            if ($reason !== false) {
                $skipped[] = ['date' => $date->toDateString(), 'reason' => $reason];
            }
        }

        return $skipped;
    }

    /**
     * False when the day is a delivery day; else the stop's name (null for a weekly off day).
     *
     * @param  list<StopPeriodRule>  $stops
     * @param  list<int>  $weeklyOffDays
     */
    private function stopReason(CarbonInterface $date, array $stops, array $weeklyOffDays): string|false|null
    {
        if (in_array($date->dayOfWeek, $weeklyOffDays, true)) {
            return null;
        }

        foreach ($stops as $stop) {
            if ($stop->covers($date)) {
                return $stop->name;
            }
        }

        return false;
    }
}
