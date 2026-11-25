<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Promotion concepts that exist centrally and per store (decision #65 b).
 */
enum PromotionKind: string
{
    use HasEnumHelpers;

    case Coupon = 'coupon';
    case FlashSale = 'flash_sale';
}
