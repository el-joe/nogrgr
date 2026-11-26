<?php

namespace App\Modules\Engagement\Repositories\Eloquent;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Engagement\Enums\ReviewSort;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Engagement\Repositories\Contracts\ProductReviewRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ProductReview>
 */
class ProductReviewRepository extends BaseRepository implements ProductReviewRepositoryContract
{
    /**
     * Order states in which every line counts as delivered to the customer.
     */
    public const DELIVERED_STATUSES = [OrderStatus::Delivered, OrderStatus::Completed];

    protected function model(): string
    {
        return ProductReview::class;
    }

    public function approvedPage(int $storeProductId, ReviewSort $sort, ?int $rating, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->query()
            ->where('store_product_id', $storeProductId)
            ->where('status', ReviewStatus::Approved->value)
            ->when($rating !== null, fn (Builder $query) => $query->where('rating', $rating))
            ->when($sort === ReviewSort::Highest, fn (Builder $query) => $query->orderByDesc('rating'))
            ->when($sort === ReviewSort::Lowest, fn (Builder $query) => $query->orderBy('rating'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function histograms(array $storeProductIds): array
    {
        if ($storeProductIds === []) {
            return [];
        }

        $result = [];
        $rows = $this->query()->toBase()
            ->selectRaw('store_product_id, rating, COUNT(*) as aggregate')
            ->whereIn('store_product_id', array_values(array_unique($storeProductIds)))
            ->where('status', ReviewStatus::Approved->value)
            ->groupBy('store_product_id', 'rating')
            ->get();

        foreach ($rows as $row) {
            $result[(int) $row->store_product_id][(int) $row->rating] = (int) $row->aggregate;
        }

        return $result;
    }

    public function topRatedIds(int $limit, int $minCount): array
    {
        return $this->query()->toBase()
            ->selectRaw('store_product_id, AVG(rating) as average, COUNT(*) as reviews')
            ->where('status', ReviewStatus::Approved->value)
            ->groupBy('store_product_id')
            ->havingRaw('COUNT(*) >= ?', [max(1, $minCount)])
            ->orderByDesc('average')
            ->orderByDesc('reviews')
            ->orderBy('store_product_id')
            ->limit($limit)
            ->pluck('store_product_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function deliveredLines(int $customerId, int $storeProductId): Collection
    {
        return OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $customerId)
            ->whereIn('orders.status', array_map(static fn (OrderStatus $status): string => $status->value, self::DELIVERED_STATUSES))
            ->where('order_items.store_product_id', $storeProductId)
            ->orderBy('orders.delivered_at')
            ->orderBy('order_items.id')
            ->get();
    }

    public function reviewedItemIds(array $orderItemIds): array
    {
        if ($orderItemIds === []) {
            return [];
        }

        return $this->query()->whereIn('order_item_id', $orderItemIds)->pluck('order_item_id')->map(static fn ($id): int => (int) $id)->all();
    }
}
