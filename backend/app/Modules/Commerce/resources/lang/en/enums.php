<?php

return [
    'coupon_type' => ['percentage' => 'Percentage', 'fixed' => 'Fixed amount', 'free_shipping' => 'Free shipping'],
    'coupon_status' => ['active' => 'Active', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'used_up' => 'Used up', 'inactive' => 'Off'],
    'flash_sale_status' => ['live' => 'Live', 'scheduled' => 'Scheduled', 'ended' => 'Ended', 'inactive' => 'Off'],
    'promotion_scope' => ['all' => 'All stores', 'selected' => 'Selected stores'],
    'discount_source' => ['central' => 'Platform', 'vendor' => 'Store'],
    'promotion_kind' => ['coupon' => 'Coupon', 'flash_sale' => 'Flash sale'],
    'profit_type' => ['percentage' => 'Percentage', 'fixed' => 'Fixed'],
    'coupon_rejection' => [
        'not_found' => 'Not found', 'inactive' => 'Inactive', 'not_started' => 'Not started', 'expired' => 'Expired',
        'country_not_allowed' => 'Country not allowed', 'minimum_spend' => 'Below minimum spend', 'usage_limit_reached' => 'Usage limit reached',
        'customer_limit_reached' => 'Customer limit reached', 'first_order_only' => 'First order only', 'sign_in_required' => 'Sign-in required',
        'nothing_to_discount' => 'Nothing to discount',
    ],
];
