<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Payment request raised by the platform on a brand/manufacturing request; paid through a gateway in P5.
 */
enum PaymentRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
