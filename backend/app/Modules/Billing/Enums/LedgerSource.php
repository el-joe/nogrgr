<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What produced a ledger transaction (ADM-053 source filter).
 */
enum LedgerSource: string
{
    use HasEnumHelpers;

    case Order = 'order';
    case Refund = 'refund';
    case Settlement = 'settlement';
    case Payout = 'payout';
    case Subscription = 'subscription';
    case Addon = 'addon';
}
