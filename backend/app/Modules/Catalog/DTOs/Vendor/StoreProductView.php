<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\StoreProduct;
use App\Shared\Money\Money;

/**
 * One store product as read by StoreCatalogReader: for imported products the CENTRAL row
 * merged with the store override (override wins per field); for own products the tenant row.
 */
final readonly class StoreProductView
{
    /**
     * @param  list<string>  $imageUrls
     * @param  list<int>  $categoryIds
     * @param  list<int>  $badgeIds
     * @param  list<StoreVariantView>  $variants  active central variants (empty for own products / simple products)
     */
    public function __construct(
        public StoreProduct $product,
        public ?Product $central,
        public ?ProductOverride $override,
        public string $name,
        public ?string $summary,
        public ?string $description,
        public ?string $sku,
        public array $imageUrls,
        public int $stock,
        public int $weightGrams,
        public bool $weightOverridden,
        public array $categoryIds,
        public array $badgeIds,
        public array $variants,
        public bool $available,
    ) {}

    public function id(): int
    {
        return $this->product->id;
    }

    public function isOwn(): bool
    {
        return $this->product->isOwn();
    }

    public function primaryImageUrl(): ?string
    {
        return $this->imageUrls[0] ?? null;
    }

    public function costBasis(): ?Money
    {
        return $this->central?->costBasis();
    }

    public function variant(int $centralVariantId): ?StoreVariantView
    {
        foreach ($this->variants as $variant) {
            if ($variant->id() === $centralVariantId) {
                return $variant;
            }
        }

        return null;
    }
}
