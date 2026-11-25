<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Models\StockReservation;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<StockReservation>
 */
interface StockReservationRepositoryContract extends RepositoryContract
{
    /**
     * Units held on a product/variant: live reservations + committed (paid, not yet
     * fulfilled). Central products count every store's holds; own products only this store's.
     *
     * @param  list<int>  $productIds
     * @return array<string, int> "productId:variantKey" => units
     */
    public function heldUnits(StoreProductSource $source, array $productIds): array;

    /**
     * Locks the stock rows of the given products (central `products` or tenant
     * `store_products`) for the surrounding transaction.
     *
     * @param  list<int>  $productIds
     */
    public function lockProducts(StoreProductSource $source, array $productIds): void;

    /**
     * @return Collection<int, StockReservation>
     */
    public function forGroup(string $checkoutGroup, ?StockReservationStatus $status = null): Collection;

    public function setStatus(StockReservation $reservation, StockReservationStatus $status): void;

    public function connectionName(StoreProductSource $source): ?string;

    /**
     * On-hand stock per "productId:variantKey" (variant key 0 = the product itself);
     * null = stock not managed (unlimited). Central = central products / variants, own =
     * store_products / store_product_variants (a variant without its own stock uses the product's).
     *
     * @param  list<int>  $productIds
     * @return array<string, int|null>
     */
    public function stockLevels(StoreProductSource $source, array $productIds): array;

    /**
     * Subtracts sold units from an own product (variant) stock.
     */
    public function decrementOwnStock(int $storeProductId, int $variantKey, int $quantity): void;

    /**
     * Reserved holds past their deadline, every store: tenant id => checkout groups.
     *
     * @return array<int, list<string>>
     */
    public function expiredGroupsByTenant(int $limit = 500): array;
}
