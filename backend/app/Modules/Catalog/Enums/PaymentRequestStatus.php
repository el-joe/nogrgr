<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Payment request on a manufacturing / brand request; settled through its Billing invoice.
 */
enum PaymentRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
