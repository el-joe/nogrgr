<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who moved an order to a status (order_status_history.actor_type).
 */
enum ActorType: string
{
    use HasEnumHelpers;

    case Customer = 'customer';
    case Vendor = 'vendor';
    case Admin = 'admin';
    case System = 'system';
    case Gateway = 'gateway';
}
