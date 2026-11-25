<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why the pay button is disabled / place order is refused (spec 06 §2, spec 08 §3 guard
 * order): empty cart → address → lines → shipping → gateway.
 */
enum CheckoutBlock: string
{
    use HasEnumHelpers;

    case EmptyCart = 'empty_cart';
    case SignInRequired = 'sign_in_required';
    case NoAddress = 'no_address';
    case UnavailableItems = 'unavailable_items';
    case ShippingUnavailable = 'shipping_unavailable';
    case NoGateway = 'no_gateway';
    case GatewayUnavailable = 'gateway_unavailable';
    case CouponInvalid = 'coupon_invalid';
    case StockChanged = 'stock_changed';

    public function message(): string
    {
        return (string) __('commerce::checkout.blocks.'.$this->value);
    }
}
