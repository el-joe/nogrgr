<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The on-hand stock of a store-own product changed inside its store (inventory page,
 * product form, a paid order deducting it). Tenant context; listener: stock alerts
 * (low / out of stock to the team, back in stock to subscribers).
 */
final class StoreStockChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $storeProductId,
        public readonly int $previousStock,
        public readonly int $currentStock,
    ) {}
}
