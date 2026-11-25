<?php

namespace App\Modules\Commerce\DTOs\Cart;

use App\Shared\DTOs\BaseDTO;

final class AddCartItemData extends BaseDTO
{
    public function __construct(
        public readonly int $storeProductId,
        public readonly ?int $variantId = null,
        public readonly int $quantity = 1,
    ) {}
}
