<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store-owned flash sales (tenant DB).
 *
 * @extends RepositoryContract<StoreFlashSale>
 */
interface StoreFlashSaleRepositoryContract extends RepositoryContract
{
    /**
     * Sales with their product and country rows, optionally only those covering one of
     * the products.
     *
     * @param  list<ProductRef>|null  $products
     * @return Collection<int, StoreFlashSale>
     */
    public function listing(?array $products = null, bool $activeOnly = false): Collection;

    /**
     * @param  list<ProductRef>  $products
     * @param  list<int>  $countryIds
     */
    public function syncTargets(StoreFlashSale $sale, array $products, array $countryIds): void;

    /**
     * Sales that have not ended yet (the attach-from-product modal, VEN-041b).
     *
     * @return Collection<int, StoreFlashSale>
     */
    public function openSales(): Collection;

    /**
     * Adds one product to a sale; false when it is already there.
     */
    public function attachProduct(StoreFlashSale $sale, ProductRef $product): bool;
}
