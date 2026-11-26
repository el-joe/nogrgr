<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Modules\Catalog\Enums\StoreCategorySource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Support\StoreCategoryRef;

/**
 * One category as the store sees it (decision #74): a central category (read-only content,
 * the store's visibility / position) or a store-own category. Built by StoreTaxonomyReader.
 */
final readonly class StoreCategoryRow
{
    /**
     * @param  list<int>  $productIds  store product ids in the category, in category order
     */
    public function __construct(
        public StoreCategoryRef $ref,
        public string $name,
        public string $path,
        public ?string $imageUrl,
        public bool $visible,
        public bool $featured,
        public ?int $sortOrder,
        public array $productIds,
        public ?Category $central = null,
        public ?StoreCategory $own = null,
    ) {}

    public function source(): StoreCategorySource
    {
        return $this->ref->source;
    }

    public function isOwn(): bool
    {
        return $this->own !== null;
    }

    public function productCount(): int
    {
        return count($this->productIds);
    }
}
