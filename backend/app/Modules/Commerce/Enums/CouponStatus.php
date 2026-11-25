<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Derived coupon state shown in lists (Figma ADM-025): inactive switch, not started,
 * live, expired, or usage limit reached.
 */
enum CouponStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case UsedUp = 'used_up';
    case Inactive = 'inactive';

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'active',
            self::Scheduled => 'info',
            self::Expired, self::Inactive => 'inactive',
            self::UsedUp => 'inactive',
        };
    }
}
