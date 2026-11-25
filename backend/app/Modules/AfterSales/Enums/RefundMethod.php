<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * original_payment = back through the gateway that captured the order (Stripe / PayPal
 * drivers); manual = paid outside the platform (cash on delivery, bank transfer) and
 * marked completed by staff.
 */
enum RefundMethod: string
{
    use HasEnumHelpers;

    case OriginalPayment = 'original_payment';
    case Manual = 'manual';
}
