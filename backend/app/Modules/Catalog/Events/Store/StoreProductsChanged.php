<?php

namespace App\Modules\Catalog\Events\Store;

use App\Modules\Catalog\Enums\StoreProductChange;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Store products of one tenant changed (fired after commit, decision #64). Listeners: the
 * storefront catalog cache (P7). Ids are tenant `store_products.id`; for removals
 * `centralProductIds` carries the central ids that left the store.
 */
final class StoreProductsChanged
{
    use Dispatchable;

    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $centralProductIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly StoreProductChange $change,
        public readonly array $productIds,
        public readonly array $centralProductIds = [],
    ) {}
}
