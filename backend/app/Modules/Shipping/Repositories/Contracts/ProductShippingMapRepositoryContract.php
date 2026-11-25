<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Catalog\Models\Product;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Closure;

/**
 * Reads central product / variant weights and writes their precomputed
 * fixed_shipping_costs maps (no model events: the map is derived data).
 *
 * @extends RepositoryContract<Product>
 */
interface ProductShippingMapRepositoryContract extends RepositoryContract
{
    /**
     * Calls $callback with chunks of [id => weight_grams|null] (products, then variants).
     *
     * @param  list<int>|null  $productIds  null = the whole catalog
     * @param  Closure(string $table, array<int, int|null> $weights): void  $callback
     */
    public function chunkWeights(?array $productIds, int $size, Closure $callback): void;

    /**
     * @param  'products'|'product_variants'  $table
     * @param  array<string, int>|null  $map
     */
    public function saveMap(string $table, int $id, ?array $map): void;
}
