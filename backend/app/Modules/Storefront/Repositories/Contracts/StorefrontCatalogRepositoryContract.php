<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreProduct;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Storefront reads of the store catalog (tenant store_products ⊕ central categories) and
 * of the order lines used for "best selling" rankings. Product content is merged by
 * Catalog\Services\Vendor\StoreCatalogReader; this repository only selects rows.
 *
 * @extends RepositoryContract<StoreProduct>
 */
interface StorefrontCatalogRepositoryContract extends RepositoryContract
{
    /**
     * Active store products in store order, with every StoreCatalogReader relation, in chunks.
     *
     * @param  callable(Collection<int, StoreProduct>): void  $callback
     */
    public function eachActiveChunk(int $size, callable $callback): void;

    public function findActive(int $id): ?StoreProduct;

    /**
     * Units sold per store product since a date (orders that were paid, not cancelled/refunded).
     *
     * @return array<int, int> store_product_id => units
     */
    public function unitsSoldSince(CarbonInterface $since): array;

    /**
     * Active central categories (menu + listing pages), ordered for the tree.
     *
     * @return Collection<int, Category>
     */
    public function activeCategories(): Collection;
}
