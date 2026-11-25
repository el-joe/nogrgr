<?php

namespace App\Modules\Commerce\DTOs\Promotions;

use App\Modules\Commerce\Enums\DiscountSource;

/**
 * Best live flash sale of one product: its % and owner (order_items.discount_source).
 */
final readonly class FlashDiscount
{
    public function __construct(
        public float $percentage,
        public DiscountSource $source,
        public int $flashSaleId,
    ) {}
}
