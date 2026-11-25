<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Pricing\FixedShipping;
use App\Modules\Shipping\Repositories\Contracts\FixedShippingCostRepositoryContract;
use App\Modules\Shipping\Repositories\Contracts\ProductShippingMapRepositoryContract;

/**
 * Stores {country_id: minor} = FixedShipping::costMap(weight, rates) on central products and
 * variants (old SyncProductFixedShippingCosts). Variants without their own weight keep null
 * and fall back to the product's map.
 */
class ProductFixedShippingSync
{
    public const CHUNK = 500;

    public function __construct(
        private readonly FixedShippingCostRepositoryContract $costs,
        private readonly ProductShippingMapRepositoryContract $products,
    ) {}

    /**
     * @param  list<int>|null  $productIds  null = whole catalog
     * @return int rows written
     */
    public function sync(?array $productIds = null): int
    {
        $rates = $this->costs->ratesByCountry();
        $written = 0;

        $this->products->chunkWeights($productIds, self::CHUNK, function (string $table, array $weights) use ($rates, &$written): void {
            foreach ($weights as $id => $weight) {
                /** @var 'products'|'product_variants' $table */
                $this->products->saveMap($table, (int) $id, FixedShipping::costMap($weight, $rates));
                $written++;
            }
        });

        return $written;
    }
}
