<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Enums\CouponType;
use App\Shared\Money\Money;
use Carbon\CarbonInterface;

/**
 * Display strings shared by the central and store promotion lists (ADM-025 / 026,
 * VEN-040 / 041): "10% off", "15% off (max $20.00)", "$5.00 off", "Free shipping",
 * "01 Nov – 30 Nov", "1,204 / ∞", "in 5h 12m".
 */
final class PromotionFormatter
{
    public static function discount(CouponType $type, ?float $percentage, ?Money $amount, ?Money $max): string
    {
        return match ($type) {
            CouponType::Percentage => $max !== null && $max->isPositive()
                ? __('commerce::coupons.labels.percent_max', ['value' => self::percent($percentage), 'max' => $max->format()])
                : __('commerce::coupons.labels.percent', ['value' => self::percent($percentage)]),
            CouponType::Fixed => __('commerce::coupons.labels.fixed', ['amount' => ($amount ?? Money::zero())->format()]),
            CouponType::FreeShipping => __('commerce::coupons.labels.free_shipping'),
        };
    }

    public static function percent(?float $value): string
    {
        $value ??= 0.0;

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }

    public static function dates(?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $format = static fn (CarbonInterface $date): string => $date->locale(app()->getLocale())->isoFormat('DD MMM');

        return match (true) {
            $from !== null && $to !== null => $format($from).' – '.$format($to),
            $from !== null => __('commerce::promotions.from_date', ['date' => $format($from)]),
            $to !== null => __('commerce::promotions.until_date', ['date' => $format($to)]),
            default => __('commerce::promotions.always'),
        };
    }

    public static function usage(int $used, ?int $limit): string
    {
        $number = static fn (int $value): string => number_format($value);

        return $number($used).' / '.($limit === null ? '∞' : $number($limit));
    }

    /**
     * Time left until $end ("in 2d 4h", "in 5h 12m"), or the end date when far away.
     */
    public static function endsIn(CarbonInterface $end, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        $minutes = max(0, (int) $now->diffInMinutes($end, false));

        if ($minutes >= 60 * 24 * 7) {
            return $end->locale(app()->getLocale())->isoFormat('DD MMM YYYY');
        }

        $days = intdiv($minutes, 60 * 24);
        $hours = intdiv($minutes % (60 * 24), 60);
        $mins = $minutes % 60;

        return $days > 0
            ? __('commerce::promotions.in_days', ['days' => $days, 'hours' => $hours])
            : __('commerce::promotions.in_hours', ['hours' => $hours, 'minutes' => $mins]);
    }
}
