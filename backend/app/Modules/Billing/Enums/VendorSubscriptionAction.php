<?php

namespace App\Modules\Billing\Enums;

/**
 * What a store pays for from Subscription & balance (VEN-060a): renew the current
 * plan, subscribe / change to another plan, or pay one of its unpaid invoices.
 */
enum VendorSubscriptionAction: string
{
    case Renew = 'renew';
    case Change = 'change';
    case Invoice = 'invoice';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
