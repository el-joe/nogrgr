<?php

namespace App\Modules\Engagement\Services;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Engagement\Exceptions\EngagementException;
use App\Modules\Engagement\Models\CustomerFavorite;
use App\Modules\Engagement\Repositories\Contracts\FavoriteRepositoryContract;
use App\Modules\Identity\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer favorites (SF-ECO-033, tenant context): toggle from any product card, the
 * favorited ids for server-side heart state, and the favorites page (paginated ids —
 * the page reads the products through StoreCatalogReader).
 */
class FavoritesService
{
    public function __construct(private readonly FavoriteRepositoryContract $favorites) {}

    /**
     * @return array{favorited: bool, count: int}
     */
    public function toggle(Customer $customer, int $storeProductId): array
    {
        if ($this->favorites->exists($customer->id, $storeProductId)) {
            $this->favorites->remove($customer->id, $storeProductId);
            $favorited = false;
        } else {
            if (StoreProduct::query()->whereKey($storeProductId)->where('is_active', true)->doesntExist()) {
                throw EngagementException::text('favorites.errors.unavailable', 'unavailable', 404);
            }

            $this->favorites->add($customer->id, $storeProductId);
            $favorited = true;
        }

        return ['favorited' => $favorited, 'count' => $this->favorites->count($customer->id)];
    }

    public function remove(Customer $customer, int $storeProductId): void
    {
        $this->favorites->remove($customer->id, $storeProductId);
    }

    /**
     * @return list<int> store product ids (guests: none)
     */
    public function idsFor(?Customer $customer): array
    {
        return $customer === null ? [] : $this->favorites->ids($customer->id);
    }

    public function count(?Customer $customer): int
    {
        return $customer === null ? 0 : $this->favorites->count($customer->id);
    }

    /**
     * Favorites page: rows (store_product_id, created_at) newest first.
     *
     * @return LengthAwarePaginator<int, CustomerFavorite>
     */
    public function forCustomer(Customer $customer, int $perPage = 24, int $page = 1): LengthAwarePaginator
    {
        return $this->favorites->page($customer->id, $perPage, $page);
    }
}
