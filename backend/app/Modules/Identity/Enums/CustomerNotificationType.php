<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Notification topics a customer can switch on/off per channel (/account/notifications).
 */
enum CustomerNotificationType: string
{
    use HasEnumHelpers;

    case OrderUpdates = 'order_updates';
    case ShippingUpdates = 'shipping_updates';
    case Returns = 'returns';
    case PriceDrops = 'price_drops';
    case BackInStock = 'back_in_stock';
    case Promotions = 'promotions';

    /**
     * Default when the customer never saved a choice: service messages on,
     * marketing (promotions) off until the customer opts in.
     */
    public function defaultEnabled(): bool
    {
        return $this !== self::Promotions;
    }
}
