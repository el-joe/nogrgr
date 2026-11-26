<?php

namespace App\Modules\Reporting\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Vendor analytics pages: the four tabs of `vendor.analytics` (VEN-045a…d, Figma 12:16223
 * order) and the return analytics page under Orders (VEN-024), which shares the engine.
 */
enum VendorAnalyticsTab: string
{
    use HasEnumHelpers;

    case Orders = 'orders';
    case Clv = 'clv';
    case Shipping = 'shipping';
    case Profitability = 'profitability';
    case Returns = 'returns';

    public function label(): string
    {
        return __('reporting::vendor_analytics.tabs.'.$this->value);
    }

    /**
     * Tabs of the Analytics page (return analytics lives under Orders).
     *
     * @return list<self>
     */
    public static function analyticsTabs(): array
    {
        return [self::Orders, self::Clv, self::Shipping, self::Profitability];
    }
}
