<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Best-selling period tabs (Figma 1:1191: within last 30 / 14 / 7 days).
 */
enum SalesPeriod: int
{
    use HasEnumHelpers;

    case Days30 = 30;
    case Days14 = 14;
    case Days7 = 7;
}
