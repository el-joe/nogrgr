<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why a coupon cannot be applied to a cart (CouponEligibilityService).
 */
enum CouponRejection: string
{
    use HasEnumHelpers;

    case NotFound = 'not_found';
    case Inactive = 'inactive';
    case NotStarted = 'not_started';
    case Expired = 'expired';
    case CountryNotAllowed = 'country_not_allowed';
    case MinimumSpend = 'minimum_spend';
    case UsageLimitReached = 'usage_limit_reached';
    case CustomerLimitReached = 'customer_limit_reached';
    case FirstOrderOnly = 'first_order_only';
    case SignInRequired = 'sign_in_required';
    case NothingToDiscount = 'nothing_to_discount';
}
