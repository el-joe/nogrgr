<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\VariantOverride;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Store overrides of central products / variants (TENANT DB, decision #65). Call inside
 * the store's tenant context.
 *
 * @extends RepositoryContract<ProductOverride>
 */
interface ProductOverrideRepositoryContract extends RepositoryContract
{
    public function forProduct(int $centralProductId): ?ProductOverride;

    public function forVariant(int $centralVariantId): ?VariantOverride;

    public function productRow(int $centralProductId): ProductOverride;

    public function variantRow(int $centralProductId, int $centralVariantId): VariantOverride;
}
