<?php

namespace App\Modules\Commerce\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A product was added to a storefront / Store API cart (CartService::add). Listener:
 * Reporting product performance facts (ADM-070j).
 */
final class CartItemAdded
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $storeProductId,
        public readonly int $quantity,
    ) {}
}
