<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who owns (and absorbs) a coupon or flash-sale discount (ORDER_FORMULAS.md §2):
 * central (platform share) or the store (vendor share). Stored as
 * `order_items.discount_source` / `orders.coupon_source` and as the `source` tag of
 * merged central + store promotions (decision #65).
 */
enum DiscountSource: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Vendor = 'vendor';
}
