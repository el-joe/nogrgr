<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where an opportunity product is promoted in one market (select markets modal, VEN-015a);
 * read by the storefront home sections (P7).
 */
enum MarketPlacement: string
{
    use HasEnumHelpers;

    case Trending = 'trending';
    case FlashSale = 'flash_sale';
    case NewIn = 'new_in';
    case Category = 'category';
}
