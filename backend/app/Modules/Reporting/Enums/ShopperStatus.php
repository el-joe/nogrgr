<?php

namespace App\Modules\Reporting\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Status of a shopper on Admin › Vendors › Shoppers (ADM-043), derived from order facts:
 * new = first order in the last 30 days, active = an order in the last 90 days,
 * inactive = no order for 90 days.
 */
enum ShopperStatus: string
{
    use HasEnumHelpers;

    case New = 'new';
    case Active = 'active';
    case Inactive = 'inactive';

    public const NEW_DAYS = 30;

    public const ACTIVE_DAYS = 90;

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Active => 'success',
            self::Inactive => 'neutral',
        };
    }

    public static function of(DateTimeInterface $firstOrderAt, DateTimeInterface $lastOrderAt, DateTimeInterface $now): self
    {
        $now = CarbonImmutable::instance($now);

        if ($firstOrderAt >= $now->subDays(self::NEW_DAYS)) {
            return self::New;
        }

        return $lastOrderAt >= $now->subDays(self::ACTIVE_DAYS) ? self::Active : self::Inactive;
    }
}
