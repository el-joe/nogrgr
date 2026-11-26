<?php

namespace App\Modules\Engagement\Repositories\Contracts;

use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Engagement\Enums\ReviewSort;
use App\Modules\Engagement\Models\ProductReview;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product reviews (tenant DB) + the delivered order lines that make a customer eligible.
 *
 * @extends RepositoryContract<ProductReview>
 */
interface ProductReviewRepositoryContract extends RepositoryContract
{
    /**
     * Approved reviews of a product, optionally one star rating only.
     *
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function approvedPage(int $storeProductId, ReviewSort $sort, ?int $rating, int $perPage, int $page): LengthAwarePaginator;

    /**
     * Approved review count per star (5..1) of each product, in ONE query.
     *
     * @param  list<int>  $storeProductIds
     * @return array<int, array<int, int>> store product id => [star => count]
     */
    public function histograms(array $storeProductIds): array;

    /**
     * @return list<int> store product ids by approved average desc, count desc
     */
    public function topRatedIds(int $limit, int $minCount): array;

    /**
     * The customer's delivered (delivered / completed order) lines of the product, oldest first.
     *
     * @return Collection<int, OrderItem>
     */
    public function deliveredLines(int $customerId, int $storeProductId): Collection;

    /**
     * @param  list<int>  $orderItemIds
     * @return list<int> the order item ids that already have a review
     */
    public function reviewedItemIds(array $orderItemIds): array;
}
