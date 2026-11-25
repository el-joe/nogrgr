<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A "Pay platform" payment of one order (order_settlements.status).
 */
enum SettlementStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
