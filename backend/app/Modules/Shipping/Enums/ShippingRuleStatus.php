<?php

namespace App\Modules\Shipping\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A shipping rule is either live (used by quotes) or a draft (ignored by quotes).
 */
enum ShippingRuleStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Draft = 'draft';
}
