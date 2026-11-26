<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Quotas a plan grants (null value = unlimited).
 */
enum PlanLimit: string
{
    use HasEnumHelpers;

    case Products = 'products';
    case Categories = 'categories';
    case Staff = 'staff';
    case Languages = 'languages';
    case Countries = 'countries';
    case Banners = 'banners';
    case OrdersPerMonth = 'orders_per_month';
    case AiCalls = 'ai_calls';
    case ImageSearches = 'image_searches';
    case AiLogos = 'ai_logos';
    case AiAds = 'ai_ads';
}
