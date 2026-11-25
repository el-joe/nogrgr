<?php

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\CountryAvailability;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Admin create / edit of a central product (ADM-011, every tab). Money in USD minor
 * units: `priceMinor` is what tenants pay (cost basis), `compareAtMinor` the optional
 * higher regular price, `costMinor` the platform's purchase cost. `stock` is
 * [branch_id][variant key | "base"] => quantity.
 */
final class ProductData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $summary
     * @param  array<string, string>  $description
     * @param  array<string, string>  $metaTitle
     * @param  array<string, string>  $metaDescription
     * @param  array<string, string>  $metaKeywords
     * @param  list<int>  $countryIds
     * @param  list<int>  $categoryIds
     * @param  list<int>  $labelIds
     * @param  list<string>  $badges  BadgeType values
     * @param  list<int>  $tenantIds
     * @param  list<ProductVariantData>  $variants
     * @param  list<int>  $keepMediaIds  kept gallery images, in order
     * @param  list<UploadedFile>  $newImages
     * @param  array<int, array<string, int>>  $stock
     */
    public function __construct(
        public readonly array $name,
        public readonly string $sku,
        public readonly ?string $slug,
        public readonly ProductStatus $status,
        public readonly int $priceMinor,
        public readonly ?int $compareAtMinor,
        public readonly int $costMinor,
        public readonly ?int $weightGrams,
        public readonly bool $isTaxable,
        public readonly bool $requiresShipping,
        public readonly DeliveryScope $deliveryScope,
        public readonly CountryAvailability $countryAvailability,
        public readonly AssignmentScope $assignmentScope,
        public readonly bool $manageStock = true,
        public readonly int $minStock = 0,
        public readonly array $summary = [],
        public readonly array $description = [],
        public readonly array $metaTitle = [],
        public readonly array $metaDescription = [],
        public readonly array $metaKeywords = [],
        public readonly array $countryIds = [],
        public readonly array $categoryIds = [],
        public readonly array $labelIds = [],
        public readonly array $badges = [],
        public readonly array $tenantIds = [],
        public readonly array $variants = [],
        public readonly array $keepMediaIds = [],
        public readonly array $newImages = [],
        public readonly array $stock = [],
    ) {}
}
