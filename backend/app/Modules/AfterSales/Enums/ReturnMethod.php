<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How the customer sends the item back (return form, SF-ECO-030; old project parity).
 */
enum ReturnMethod: string
{
    use HasEnumHelpers;

    case CourierPickup = 'courier_pickup';
    case DropOff = 'drop_off';
    case ShipBack = 'ship_back';
}
