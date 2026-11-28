<?php

namespace App\Modules\Storefront\Listeners;

use App\Modules\Catalog\Events\BadgesChanged;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Storefront\Services\Catalog\ProductIndex;

/**
 * Badges (and the products carrying them) are part of the cached storefront product index:
 * an admin or vendor change rebuilds it on the next read instead of after the TTL.
 */
class ForgetProductIndexOnCatalogChange
{
    public function handle(BadgesChanged|StoreProductsChanged|ProductUpdated $event): void
    {
        ProductIndex::bump($event instanceof ProductUpdated ? null : $event->tenantId);
    }
}
