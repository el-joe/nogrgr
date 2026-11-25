<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Direction of a ledger transaction from the store's side: credit raises what the platform owes it, debit lowers it, reversal undoes an earlier credit / debit (refund, cancellation, cancelled payout).
 */
enum LedgerDirection: string
{
    use HasEnumHelpers;

    case Credit = 'credit';
    case Debit = 'debit';
    case Reversal = 'reversal';

    public function tone(): string
    {
        return match ($this) {
            self::Credit => 'success',
            self::Debit => 'info',
            self::Reversal => 'danger',
        };
    }
}
