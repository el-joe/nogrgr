<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Which stores a central coupon / flash sale applies to: every store, or only the
 * selected ones.
 */
enum PromotionScope: string
{
    use HasEnumHelpers;

    case AllStores = 'all';
    case SelectedStores = 'selected';
}
