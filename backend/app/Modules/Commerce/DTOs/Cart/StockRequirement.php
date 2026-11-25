<?php

namespace App\Modules\Commerce\DTOs\Cart;

use App\Modules\Catalog\Enums\StoreProductSource;

/**
 * Units of one product / variant an order needs (stock reservation input).
 */
final readonly class StockRequirement
{
    public function __construct(
        public string $orderUuid,
        public StoreProductSource $source,
        public int $productId,
        public int $variantKey,
        public int $quantity,
    ) {}

    public function key(): string
    {
        return $this->productId.':'.$this->variantKey;
    }
}
