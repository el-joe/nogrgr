<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who received the customer's money (ORDER_FORMULAS.md §2 `collected_by`): the store's
 * own gateway or cash on delivery → vendor; the platform gateway → platform.
 */
enum CollectedBy: string
{
    use HasEnumHelpers;

    case Vendor = 'vendor';
    case Platform = 'platform';

    public static function forOrder(?PaymentSource $source, PaymentStatus $payment): self
    {
        return $source === PaymentSource::Central && $payment !== PaymentStatus::CashOnDelivery ? self::Platform : self::Vendor;
    }
}
