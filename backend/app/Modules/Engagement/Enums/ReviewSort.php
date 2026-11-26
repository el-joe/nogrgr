<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Order of the product reviews list (SF-ECO-019a).
 */
enum ReviewSort: string
{
    use HasEnumHelpers;

    case Newest = 'newest';
    case Highest = 'highest';
    case Lowest = 'lowest';
}
