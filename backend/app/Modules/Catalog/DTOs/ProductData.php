<?php

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Admin create / edit of a central product (ADM-011, #115) and the catalog importer.
 * Money in USD minor units: `priceMinor` is what tenants pay (cost basis),
 * `compareAtMinor` the optional higher regular price, `costMinor` the platform's
 * purchase cost. `deliveryScope` / `requiresShipping` null = keep the product's current
 * value (the form has no shipping section). Stock: `stock` is the explicit grid
 * [branch_id][variant key | "base"] => quantity (importer); `quantities` [variant key |
 * "base"] => quantity is written to the default branch (admin form), other branches
 * keep their levels. Media: `primaryKeepId` / `primaryImage` = the primary image section
 * (null both = the first gallery image is the primary one). `notifyStores` = tell newly
 * assigned stores (admin form only; imports never notify).
 */
final class ProductData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $label
     * @param  array<string, string>  $summary
     * @param  array<string, string>  $description
     * @param  array<string, string>  $metaTitle
     * @param  array<string, string>  $metaDescription
     * @param  array<string, string>  $metaKeywords
     * @param  list<int>  $categoryIds
     * @param  list<int>  $labelIds
     * @param  list<string>  $badges  BadgeType values
     * @param  list<int>  $tenantIds
     * @param  list<ProductVariantData>  $variants
     * @param  list<int>  $keepMediaIds  kept gallery items, in order
     * @param  list<UploadedFile>  $newImages
     * @param  array<int, array<string, int>>  $stock
     * @param  array<string, int>|null  $quantities
     */
    public function __construct(
        public readonly array $name,
        public readonly string $sku,
        public readonly ?string $slug,
        public readonly ProductStatus $status,
        public readonly int $priceMinor,
        public readonly ?int $compareAtMinor,
        public readonly ?int $costMinor,
        public readonly ?int $weightGrams,
        public readonly bool $isTaxable,
        public readonly AssignmentScope $assignmentScope,
        public readonly ?bool $requiresShipping = null,
        public readonly ?DeliveryScope $deliveryScope = null,
        public readonly bool $manageStock = true,
        public readonly int $minStock = 0,
        public readonly ?string $factory = null,
        public readonly array $label = [],
        public readonly array $summary = [],
        public readonly array $description = [],
        public readonly array $metaTitle = [],
        public readonly array $metaDescription = [],
        public readonly array $metaKeywords = [],
        public readonly array $categoryIds = [],
        public readonly array $labelIds = [],
        public readonly array $badges = [],
        public readonly array $tenantIds = [],
        public readonly array $variants = [],
        public readonly ?int $primaryKeepId = null,
        public readonly ?UploadedFile $primaryImage = null,
        public readonly array $keepMediaIds = [],
        public readonly array $newImages = [],
        public readonly array $stock = [],
        public readonly ?array $quantities = null,
        public readonly bool $notifyStores = false,
    ) {}
}
