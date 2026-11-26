<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PayoutMethod: string
{
    use HasEnumHelpers;

    case BankTransfer = 'bank_transfer';
    case Paypal = 'paypal';
    case Other = 'other';
}
