<?php

namespace App\Modules\Engagement\Repositories\Eloquent;

use App\Modules\Engagement\Models\CustomerFavorite;
use App\Modules\Engagement\Repositories\Contracts\FavoriteRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<CustomerFavorite>
 */
class FavoriteRepository extends BaseRepository implements FavoriteRepositoryContract
{
    protected function model(): string
    {
        return CustomerFavorite::class;
    }

    public function exists(int $customerId, int $storeProductId): bool
    {
        return CustomerFavorite::query()->where('customer_id', $customerId)->where('store_product_id', $storeProductId)->exists();
    }

    public function add(int $customerId, int $storeProductId): void
    {
        CustomerFavorite::query()->firstOrCreate(['customer_id' => $customerId, 'store_product_id' => $storeProductId]);
    }

    public function remove(int $customerId, int $storeProductId): void
    {
        CustomerFavorite::query()->where('customer_id', $customerId)->where('store_product_id', $storeProductId)->delete();
    }

    public function count(int $customerId): int
    {
        return CustomerFavorite::query()->where('customer_id', $customerId)->count();
    }

    public function ids(int $customerId): array
    {
        return CustomerFavorite::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('store_product_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function page(int $customerId, int $perPage, int $page): LengthAwarePaginator
    {
        return CustomerFavorite::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
