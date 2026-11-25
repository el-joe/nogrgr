<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\VariantOverride;
use App\Modules\Catalog\Repositories\Contracts\ProductOverrideRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<ProductOverride>
 */
class ProductOverrideRepository extends BaseRepository implements ProductOverrideRepositoryContract
{
    protected function model(): string
    {
        return ProductOverride::class;
    }

    public function forProduct(int $centralProductId): ?ProductOverride
    {
        return $this->query()->where('product_id', $centralProductId)->first();
    }

    public function forVariant(int $centralVariantId): ?VariantOverride
    {
        return VariantOverride::query()->where('variant_id', $centralVariantId)->first();
    }

    public function productRow(int $centralProductId): ProductOverride
    {
        return $this->query()->firstOrNew(['product_id' => $centralProductId]);
    }

    public function variantRow(int $centralProductId, int $centralVariantId): VariantOverride
    {
        return VariantOverride::query()->firstOrNew(['variant_id' => $centralVariantId], ['product_id' => $centralProductId]);
    }
}
