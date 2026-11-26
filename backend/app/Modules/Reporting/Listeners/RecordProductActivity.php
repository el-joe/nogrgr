<?php

namespace App\Modules\Reporting\Listeners;

use App\Modules\Commerce\Events\CartItemAdded;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Reporting\Repositories\Contracts\ProductActivityRepositoryContract;
use App\Modules\Storefront\Events\ProductViewed;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Feeds `product_daily_facts` (ADM-070j): +1 view per product page view, +1 add-to-cart
 * per cart add, and per paid order +1 order / units / revenue (line grand total) for each
 * of its products. A paid order is counted once even if OrderPaid is replayed. Reporting
 * never breaks the shopper's request: failures are reported and swallowed.
 */
class RecordProductActivity
{
    private const PAID_MARK_DAYS = 45;

    public function __construct(private readonly ProductActivityRepositoryContract $products) {}

    public function handle(ProductViewed|CartItemAdded|OrderPaid $event): void
    {
        try {
            match (true) {
                $event instanceof ProductViewed => $this->products->increment($event->tenantId, $event->storeProductId, now()->toDateString(), $event->productName, ['views' => 1]),
                $event instanceof CartItemAdded => $this->products->increment($event->tenantId, $event->storeProductId, now()->toDateString(), null, ['cart_adds' => 1]),
                default => $this->recordOrder($event),
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function recordOrder(OrderPaid $event): void
    {
        $order = $event->order;

        if (! Cache::add('reporting:product-facts:paid:'.$event->tenantId.':'.$order->uuid, true, now()->addDays(self::PAID_MARK_DAYS))) {
            return;
        }

        $day = ($order->paid_at ?? now())->toDateString();
        $lines = [];

        foreach ($order->items as $item) {
            $line = $lines[$item->store_product_id] ?? ['name' => $item->name, 'units' => 0, 'revenue' => 0];
            $line['units'] += (int) $item->quantity;
            $line['revenue'] += (int) $item->grand_total;
            $lines[$item->store_product_id] = $line;
        }

        foreach ($lines as $storeProductId => $line) {
            $this->products->increment($event->tenantId, (int) $storeProductId, $day, $line['name'], ['orders' => 1, 'units' => $line['units'], 'revenue' => $line['revenue']]);
        }
    }
}
