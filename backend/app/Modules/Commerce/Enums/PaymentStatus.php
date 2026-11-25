<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Payment state of an order (orders.payment_status).
 */
enum PaymentStatus: string
{
    use HasEnumHelpers;

    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case CashOnDelivery = 'cod';
}
