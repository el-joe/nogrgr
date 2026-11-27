<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Jobs\FanOutCentralStockAlerts;

/**
 * Central stock change → the stores selling the product check their alerts. Only a
 * decrease (thresholds are per store) or a return from zero can alert, so other moves
 * queue nothing.
 */
class QueueCentralStockAlerts
{
    public function handle(StockChanged $event): void
    {
        $previous = $event->previousStock;

        if ($previous === null || $previous === $event->totalStock) {
            return;
        }

        if ($event->totalStock < $previous || ($previous <= 0 && $event->totalStock > 0)) {
            FanOutCentralStockAlerts::dispatch($event->productId, $previous, $event->totalStock)->afterCommit();
        }
    }
}
