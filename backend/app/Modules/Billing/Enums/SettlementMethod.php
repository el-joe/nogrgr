<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How a store paid the platform outside the gateway (ADM-050a mark settlement paid); payout_offset deducts it from what the platform owes the store.
 */
enum SettlementMethod: string
{
    use HasEnumHelpers;

    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case PayoutOffset = 'payout_offset';
    case Other = 'other';

    public function needsReference(): bool
    {
        return $this !== self::PayoutOffset;
    }
}
