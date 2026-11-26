<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ListingSort: string
{
    use HasEnumHelpers;

    case Recommended = 'recommended';
    case Relevance = 'relevance';
    case BestSelling = 'best_selling';
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Rating = 'rating';
    case Discount = 'discount';
}
