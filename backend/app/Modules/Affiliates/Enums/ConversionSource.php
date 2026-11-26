<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ConversionSource: string
{
    use HasEnumHelpers;

    case Link = 'link';
    case PromoCode = 'promo_code';
}
