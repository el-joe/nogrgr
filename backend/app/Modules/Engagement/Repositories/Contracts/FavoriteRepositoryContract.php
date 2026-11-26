<?php

namespace App\Modules\Engagement\Repositories\Contracts;

use App\Modules\Engagement\Models\CustomerFavorite;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer favorites (tenant DB).
 *
 * @extends RepositoryContract<CustomerFavorite>
 */
interface FavoriteRepositoryContract extends RepositoryContract
{
    public function exists(int $customerId, int $storeProductId): bool;

    public function add(int $customerId, int $storeProductId): void;

    public function remove(int $customerId, int $storeProductId): void;

    public function count(int $customerId): int;

    /**
     * @return list<int> store product ids, newest first
     */
    public function ids(int $customerId): array;

    /**
     * @return LengthAwarePaginator<int, CustomerFavorite> newest first
     */
    public function page(int $customerId, int $perPage, int $page): LengthAwarePaginator;
}
