<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Shared\DTOs\BaseDTO;

/**
 * Store business data of an imported central product (VEN-012): per-row markups
 * (`pricing`: variantKey => countryId => {profit_type, profit_value}; variantKey = central
 * variant id or "product"), store category / badge placement (null = keep the central
 * ones) and the active / featured flags. Central fields are never accepted.
 */
final class StoreProductUpdateData extends BaseDTO
{
    /**
     * @param  array<string, array<string, array{profit_type: string, profit_value: float|int|string}>>  $pricing
     * @param  list<int>|null  $categoryIds
     * @param  list<int>|null  $badgeIds
     */
    public function __construct(
        public readonly array $pricing = [],
        public readonly ?array $categoryIds = null,
        public readonly ?array $badgeIds = null,
        public readonly bool $isActive = true,
        public readonly bool $isFeatured = false,
    ) {}
}
