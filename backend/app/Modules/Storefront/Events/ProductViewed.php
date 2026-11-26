<?php

namespace App\Modules\Storefront\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A shopper opened a product page of a store (prefetches excluded). Listener: Reporting
 * product performance facts (ADM-070j).
 */
final class ProductViewed
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $storeProductId,
        public readonly ?string $productName = null,
    ) {}
}
