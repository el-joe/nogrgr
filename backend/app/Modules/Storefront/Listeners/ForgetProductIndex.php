<?php

namespace App\Modules\Storefront\Listeners;

use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Storefront\Services\Catalog\ProductIndex;

/**
 * A coupon / flash sale changed: the storefront product index (flash prices, deal rails) is rebuilt on the next read.
 */
class ForgetProductIndex
{
    public function handle(PromotionsChanged $event): void
    {
        ProductIndex::bump($event->tenantId);
    }
}
