<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who receives an e-mail template (Admin › Settings › Email templates tabs, Figma
 * Admin Panel 79:89188).
 */
enum EmailAudience: string
{
    use HasEnumHelpers;

    case Vendors = 'vendors';
    case Shoppers = 'shoppers';
    case Affiliates = 'affiliates';
    case Admins = 'admins';

    public function tone(): string
    {
        return match ($this) {
            self::Vendors => 'brand',
            self::Shoppers => 'info',
            self::Affiliates => 'warning',
            self::Admins => 'neutral',
        };
    }
}
