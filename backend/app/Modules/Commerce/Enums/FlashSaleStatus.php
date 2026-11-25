<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Derived flash sale state (Figma ADM-026 tabs): live now, scheduled, ended; a switched
 * off sale is inactive.
 */
enum FlashSaleStatus: string
{
    use HasEnumHelpers;

    case Live = 'live';
    case Scheduled = 'scheduled';
    case Ended = 'ended';
    case Inactive = 'inactive';

    public function badge(): string
    {
        return match ($this) {
            self::Live => 'active',
            self::Scheduled => 'info',
            self::Ended, self::Inactive => 'inactive',
        };
    }
}
