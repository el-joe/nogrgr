<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Coupon value kinds. Percentage and fixed follow OrderPricing::couponDiscount() of
 * ORDER_FORMULAS.md (percentage of items_grand_total, fixed capped at it); free shipping
 * (Figma ADM-025) takes the order's shipping charge off.
 */
enum CouponType: string
{
    use HasEnumHelpers;

    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';
}
