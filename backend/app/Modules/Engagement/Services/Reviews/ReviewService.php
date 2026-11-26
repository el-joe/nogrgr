<?php

namespace App\Modules\Engagement\Services\Reviews;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Engagement\DTOs\ReviewInputData;
use App\Modules\Engagement\Enums\ReviewIneligibility;
use App\Modules\Engagement\Enums\ReviewSort;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Events\ReviewModerated;
use App\Modules\Engagement\Events\ReviewSubmitted;
use App\Modules\Engagement\Exceptions\EngagementException;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Engagement\Repositories\Contracts\ProductReviewRepositoryContract;
use App\Modules\Engagement\Repositories\Eloquent\ProductReviewRepository;
use App\Modules\Identity\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Product reviews (tenant context): only a customer with a delivered order line of the
 * product may review it, once per order line. Published at once unless the store
 * requires approval. Moderation API (approve / reject / hide) for the store or the
 * platform — no P7 moderation page.
 */
class ReviewService
{
    public function __construct(
        private readonly ProductReviewRepositoryContract $reviews,
        private readonly ReviewSettingsService $settings,
        private readonly CustomerOrderService $orders,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function approved(int $storeProductId, ReviewSort $sort = ReviewSort::Newest, ?int $rating = null, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return $this->reviews->approvedPage($storeProductId, $sort, $rating, $perPage, $page);
    }

    /**
     * 404 unless the store sells the product (active store product).
     */
    public function assertProduct(int $storeProductId): void
    {
        if (StoreProduct::query()->whereKey($storeProductId)->where('is_active', true)->doesntExist()) {
            throw EngagementException::text('reviews.errors.product_not_found', 'product_not_found', 404);
        }
    }

    /**
     * Null = the customer may write a review of the product now.
     */
    public function ineligibility(?Customer $customer, int $storeProductId): ?ReviewIneligibility
    {
        if ($customer === null) {
            return ReviewIneligibility::Guest;
        }

        return $this->openLine($customer, $storeProductId) === null
            ? ($this->reviews->deliveredLines($customer->id, $storeProductId)->isEmpty() ? ReviewIneligibility::NotPurchased : ReviewIneligibility::AlreadyReviewed)
            : null;
    }

    /**
     * Product page "write a review": the oldest delivered line not reviewed yet.
     */
    public function submitForProduct(Customer $customer, int $storeProductId, ReviewInputData $data): ProductReview
    {
        $line = $this->openLine($customer, $storeProductId);

        if ($line === null) {
            $reason = $this->ineligibility($customer, $storeProductId) ?? ReviewIneligibility::NotPurchased;

            throw EngagementException::text($reason->textKey(), $reason->value);
        }

        return $this->store($customer, $line, $data);
    }

    /**
     * Order details "write a review" (SF-ECO-028d): one line of the customer's order.
     */
    public function submitForOrderItem(Customer $customer, string $orderUuid, int $orderItemId, ReviewInputData $data): ProductReview
    {
        $order = $this->orders->find($customer, $orderUuid);
        $line = $order->items->firstWhere('id', $orderItemId);

        if (! $line instanceof OrderItem) {
            throw EngagementException::text('reviews.errors.line_not_found', 'line_not_found', 404);
        }

        if (! in_array($order->status, ProductReviewRepository::DELIVERED_STATUSES, true)) {
            throw EngagementException::text(ReviewIneligibility::NotPurchased->textKey(), 'not_delivered');
        }

        if ($this->reviews->reviewedItemIds([$line->id]) !== []) {
            throw EngagementException::text(ReviewIneligibility::AlreadyReviewed->textKey(), ReviewIneligibility::AlreadyReviewed->value);
        }

        return $this->store($customer, $line, $data);
    }

    /**
     * The order lines among $orderItemIds that already have a review (order details page).
     *
     * @param  list<int>  $orderItemIds
     * @return list<int>
     */
    public function reviewedLines(array $orderItemIds): array
    {
        return $this->reviews->reviewedItemIds($orderItemIds);
    }

    public function approve(ProductReview $review, ?string $note = null): ProductReview
    {
        return $this->moderate($review, ReviewStatus::Approved, $note);
    }

    public function reject(ProductReview $review, ?string $note = null): ProductReview
    {
        return $this->moderate($review, ReviewStatus::Rejected, $note);
    }

    public function hide(ProductReview $review, ?string $note = null): ProductReview
    {
        return $this->moderate($review, ReviewStatus::Hidden, $note);
    }

    private function moderate(ProductReview $review, ReviewStatus $status, ?string $note): ProductReview
    {
        $from = $review->status;
        $this->reviews->update($review, [
            'status' => $status,
            'moderation_note' => $note !== null && trim($note) !== '' ? Str::limit(trim($note), 500, '') : null,
            'moderated_at' => now(),
        ]);

        if ($from !== $status) {
            ReviewModerated::dispatch($review, $from);
        }

        return $review;
    }

    private function openLine(Customer $customer, int $storeProductId): ?OrderItem
    {
        $lines = $this->reviews->deliveredLines($customer->id, $storeProductId);
        $reviewed = array_flip($this->reviews->reviewedItemIds($lines->pluck('id')->map(static fn ($id): int => (int) $id)->all()));

        return $lines->first(static fn (OrderItem $line): bool => ! isset($reviewed[$line->id]));
    }

    private function store(Customer $customer, OrderItem $line, ReviewInputData $data): ProductReview
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        try {
            $review = $this->reviews->create([
                'store_product_id' => $line->store_product_id,
                'customer_id' => $customer->id,
                'order_id' => $line->order_id,
                'order_item_id' => $line->id,
                'rating' => max(1, min(5, $data->rating)),
                'title' => $data->title !== null && trim($data->title) !== '' ? trim($data->title) : null,
                'comment' => trim($data->comment),
                'author_name' => self::authorName((string) $customer->name),
                'locale' => $data->locale,
                'status' => $this->settings->requiresApproval() ? ReviewStatus::Pending : ReviewStatus::Approved,
            ]);
        } catch (QueryException) {
            // Unique order_item_id: a concurrent submission of the same line won.
            throw EngagementException::text(ReviewIneligibility::AlreadyReviewed->textKey(), ReviewIneligibility::AlreadyReviewed->value);
        }

        ReviewSubmitted::dispatch($review, $tenantId);

        return $review;
    }

    /**
     * Public author label: first name + last initial ("Sara A.").
     */
    public static function authorName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '—';
        }

        $first = Str::limit($parts[0], 60, '');

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.' : $first;
    }
}
