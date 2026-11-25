<?php

namespace App\Modules\Shipping\Jobs;

use App\Modules\Shipping\Services\ProductFixedShippingSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-derives the fixed_shipping_costs map of central products / variants after a rate or
 * weight change. Whole-catalog runs (null ids) are unique while queued, so a burst of
 * rate edits recomputes once; chunked by ProductFixedShippingSync.
 */
class RecomputeFixedShippingCosts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    /**
     * @param  list<int>|null  $productIds  null = whole catalog
     */
    public function __construct(public readonly ?array $productIds = null) {}

    public function uniqueId(): string
    {
        return $this->productIds === null ? 'catalog' : 'products:'.implode(',', $this->productIds);
    }

    public function handle(ProductFixedShippingSync $sync): void
    {
        $sync->sync($this->productIds);
    }
}
