<?php

namespace App\Modules\Shipping\Listeners;

use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Shipping\Jobs\RecomputeFixedShippingCosts;

/**
 * A saved central product (weight may have changed) gets its fixed shipping map recomputed.
 */
class RecomputeProductFixedShipping
{
    public function handle(ProductCreated|ProductUpdated $event): void
    {
        RecomputeFixedShippingCosts::dispatch([$event->productId]);
    }
}
