<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A coupon or flash sale was created, changed or deleted (central: every targeted store
 * is affected; vendor: only $tenantId). The storefront cache (P7) listens.
 */
final class PromotionsChanged
{
    use Dispatchable;

    public function __construct(
        public readonly PromotionKind $kind,
        public readonly DiscountSource $source,
        public readonly int $promotionId,
        public readonly ?int $tenantId = null,
    ) {}
}
