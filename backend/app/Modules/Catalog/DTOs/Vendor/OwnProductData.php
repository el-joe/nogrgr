<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;
use Illuminate\Http\UploadedFile;

/**
 * Own product create / edit (VEN-016c). Translatable fields are locale => text maps;
 * `countryIds` null = sold in every store country. Images: new uploads + kept ids (in order).
 */
final class OwnProductData extends BaseDTO
{
    /**
     * @param  array<string, string|null>  $name
     * @param  array<string, string|null>  $label
     * @param  array<string, string|null>  $summary
     * @param  array<string, string|null>  $description
     * @param  list<int>|null  $countryIds
     * @param  list<int>  $categoryIds
     * @param  list<int>  $storeCategoryIds  store-own categories (decision #74)
     * @param  list<OwnVariantData>  $variants
     * @param  list<UploadedFile>  $images
     * @param  list<int>  $keepImageIds
     */
    public function __construct(
        public readonly array $name,
        public readonly array $label,
        public readonly array $summary,
        public readonly array $description,
        public readonly ?string $sku,
        public readonly ?string $slug,
        public readonly int $weightGrams,
        public readonly ?array $countryIds,
        public readonly array $categoryIds,
        public readonly Money $price,
        public readonly ?Money $salePrice,
        public readonly ?Money $costPrice,
        public readonly int $stock,
        public readonly bool $isActive,
        public readonly bool $isFeatured,
        public readonly bool $manageStock,
        public readonly bool $isTaxable,
        public readonly array $variants = [],
        public readonly array $images = [],
        public readonly array $keepImageIds = [],
        public readonly array $storeCategoryIds = [],
    ) {}
}
