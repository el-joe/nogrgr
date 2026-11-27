<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Central stock of a product changed (product form, branch import, fulfilment, restock).
 * `previousStock` = the total before the change (null when unknown). Listener:
 * QueueCentralStockAlerts (low / out of stock + back in stock in every store selling it).
 */
final class StockChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $productId,
        public readonly int $totalStock,
        public readonly ?int $branchId = null,
        public readonly ?int $previousStock = null,
    ) {}
}
