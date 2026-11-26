<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How a commission or promo discount is expressed: percentage (value in basis points,
 * 100 = 1 %) or a fixed USD amount (value in cents).
 */
enum RateType: string
{
    use HasEnumHelpers;

    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
