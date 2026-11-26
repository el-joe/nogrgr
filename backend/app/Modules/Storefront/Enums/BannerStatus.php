<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;
use Carbon\CarbonInterface;

/**
 * Derived banner state (Figma ADM-085 badges): active now, scheduled, ended, switched off.
 */
enum BannerStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Scheduled = 'scheduled';
    case Ended = 'ended';
    case Inactive = 'inactive';

    public static function derive(bool $active, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt, ?CarbonInterface $at = null): self
    {
        $at ??= now();

        return match (true) {
            $endsAt !== null && $endsAt->lessThan($at) => self::Ended,
            $startsAt !== null && $startsAt->greaterThan($at) => self::Scheduled,
            ! $active => self::Inactive,
            default => self::Active,
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'active',
            self::Scheduled => 'info',
            self::Ended, self::Inactive => 'inactive',
        };
    }

    public static function translationKey(): string
    {
        return 'engagement::enums.banner_status';
    }
}
