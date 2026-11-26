<?php

namespace App\Modules\Reporting\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Tabs of Admin › Reports (ADM-070a…070l, Figma 75:68061 / 75:69019 / 75:69998), in the
 * Figma order. The slug is the `{tab}` of `admin.reports.show`.
 */
enum ReportTab: string
{
    use HasEnumHelpers;

    case Sales = 'sales';
    case Orders = 'orders';
    case Profit = 'profit';
    case Refunds = 'refunds';
    case Shipping = 'shipping';
    case Promotions = 'promotions';
    case Subscriptions = 'subscriptions';
    case Affiliates = 'affiliates';
    case Vendors = 'vendors';
    case Products = 'products';
    case Customers = 'customers';
    case AiUsage = 'ai_usage';

    public function label(): string
    {
        return __('reporting::reports.tabs.'.$this->value);
    }

    /**
     * Whether the store / country filters narrow this tab (platform-wide data does not).
     */
    public function filtersByCountry(): bool
    {
        return ! in_array($this, [self::Subscriptions, self::Affiliates, self::Products, self::AiUsage], true);
    }
}
