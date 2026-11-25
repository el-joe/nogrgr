<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How the platform pays a store (ADM-052a).
 */
enum PayoutMethod: string
{
    use HasEnumHelpers;

    case BankTransfer = 'bank_transfer';
    case Paypal = 'paypal';
    case Wise = 'wise';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Other = 'other';
}
