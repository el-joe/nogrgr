<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Vendor › Customers list filter (VEN-025): customers who placed at least one order
 * ("buyers", the KPI card) or none yet.
 */
enum CustomerSegment: string
{
    use HasEnumHelpers;

    case Buyers = 'buyers';
    case NoOrders = 'no_orders';

    public function label(): string
    {
        return __('identity::customers.segments.'.$this->value);
    }
}
