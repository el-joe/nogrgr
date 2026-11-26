<?php

namespace App\Modules\Support\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a support ticket is about (filter on every list, subtitle of each row).
 */
enum TicketCategory: string
{
    use HasEnumHelpers;

    case Payments = 'payments';
    case Orders = 'orders';
    case Products = 'products';
    case Shipping = 'shipping';
    case Domains = 'domains';
    case StoreDesign = 'store_design';
    case Billing = 'billing';
    case Languages = 'languages';
    case Account = 'account';
    case Technical = 'technical';
    case Other = 'other';
}
