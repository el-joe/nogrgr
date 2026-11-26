<?php

namespace App\Modules\Api\Services;

use App\Modules\Engagement\Models\CustomerFavorite;
use App\Modules\Engagement\Services\FavoritesService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\Services\Catalog\ProductIndex;

/**
 * Wishlist for the app (APP-019): the customer's favorites (Engagement) resolved through
 * the storefront product index of the shopping country, newest first. Products the store
 * no longer sells there are skipped (still counted in `total` of the page meta).
 */
class MobileFavoritesService
{
    public function __construct(
        private readonly FavoritesService $favorites,
        private readonly ProductIndex $index,
    ) {}

    /**
     * @return array{items: list<IndexEntry>, ids: list<int>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function page(Customer $customer, int $perPage, int $page): array
    {
        $rows = $this->favorites->forCustomer($customer, $perPage, $page);
        $items = [];

        foreach ($rows->items() as $favorite) {
            /** @var CustomerFavorite $favorite */
            $entry = $this->index->entry($favorite->store_product_id);

            if ($entry !== null) {
                $items[] = $entry;
            }
        }

        return [
            'items' => $items,
            'ids' => $this->favorites->idsFor($customer),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()],
        ];
    }

    /**
     * @return array{favorited: bool, count: int}
     */
    public function add(Customer $customer, int $storeProductId): array
    {
        if (in_array($storeProductId, $this->favorites->idsFor($customer), true)) {
            return ['favorited' => true, 'count' => $this->favorites->count($customer)];
        }

        return $this->favorites->toggle($customer, $storeProductId);
    }

    /**
     * @return array{favorited: bool, count: int}
     */
    public function remove(Customer $customer, int $storeProductId): array
    {
        $this->favorites->remove($customer, $storeProductId);

        return ['favorited' => false, 'count' => $this->favorites->count($customer)];
    }
}
