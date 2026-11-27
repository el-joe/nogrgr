<?php

namespace App\Modules\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A published central product became available to stores that could not see it before
 * (admin form tenant assignment, #115): `allStores` = every active store except
 * `exceptTenantIds`, else exactly `tenantIds`. Fired after commit; the stores' owners and
 * staff are told to review it under Products › New products (#65 store selection: the
 * product is read from central, nothing is copied).
 */
final class ProductOfferedToStores
{
    use Dispatchable;

    /**
     * @param  list<int>  $tenantIds
     * @param  list<int>  $exceptTenantIds
     */
    public function __construct(
        public readonly int $productId,
        public readonly array $tenantIds = [],
        public readonly bool $allStores = false,
        public readonly array $exceptTenantIds = [],
    ) {}
}
