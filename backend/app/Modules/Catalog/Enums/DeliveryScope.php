<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a central product can be delivered (old products.delivery_scope).
 */
enum DeliveryScope: string
{
    use HasEnumHelpers;

    case AllZones = 'all_zones';
    case SelectedZones = 'selected_zones';
    case Digital = 'digital';
}
