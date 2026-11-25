<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Sections of the own products overview (VEN-016) and their "See all" pages (VEN-016a).
 */
enum OwnProductSection: string
{
    use HasEnumHelpers;

    case NewIn = 'new-in';
    case Featured = 'featured';
    case BestSellers = 'best-sellers';
}
