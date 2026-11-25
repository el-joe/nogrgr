<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Last connection check of a store's own gateway keys (store_payment_gateways).
 */
enum GatewayConnection: string
{
    use HasEnumHelpers;

    case Connected = 'connected';
    case NotConnected = 'not_connected';
}
