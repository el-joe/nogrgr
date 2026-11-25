<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * One own-product variant row of the create / edit form (VEN-016c).
 */
final class OwnVariantData extends BaseDTO
{
    /**
     * @param  array<string, string|null>  $title  locale => title
     */
    public function __construct(
        public readonly ?int $id,
        public readonly array $title,
        public readonly ?string $sku,
        public readonly Money $price,
        public readonly ?Money $salePrice,
        public readonly int $stock,
        public readonly bool $isActive = true,
    ) {}
}
