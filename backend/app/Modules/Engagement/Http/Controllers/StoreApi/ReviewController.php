<?php

namespace App\Modules\Engagement\Http\Controllers\StoreApi;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Exceptions\EngagementException;
use App\Modules\Engagement\Http\Requests\StoreApi\ReviewIndexRequest;
use App\Modules\Engagement\Http\Requests\StoreApi\StoreReviewRequest;
use App\Modules\Engagement\Http\Resources\StoreApi\ReviewResource;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Engagement\Services\Reviews\ReviewService;
use App\Modules\Engagement\Services\Reviews\ReviewSummaryReader;
use App\Modules\Identity\Models\Customer;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store API product reviews: list + summary + "can I write one" (product reviews modal
 * SF-ECO-019a, load more), write from the product page or an order line (SF-ECO-028d).
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ReviewSummaryReader $summaries,
    ) {}

    public function index(ReviewIndexRequest $request, int $storeProduct): JsonResponse
    {
        $this->reviews->assertProduct($storeProduct);
        $page = $this->reviews->approved($storeProduct, $request->sort(), $request->rating(), $request->perPage(), $request->page());
        $reason = $this->reviews->ineligibility(StoreApiCustomer::resolve($request), $storeProduct);

        return JsonResponder::success('', [
            'summary' => $this->summaries->summary($storeProduct),
            'items' => collect($page->items())->map(static fn (ProductReview $review): array => (new ReviewResource($review))->toArray($request))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'next_page_url' => $page->hasMorePages() ? $request->fullUrlWithQuery(['page' => $page->currentPage() + 1]) : null,
            ],
            'eligibility' => ['can_write' => $reason === null, 'reason' => $reason?->value],
        ]);
    }

    public function store(StoreReviewRequest $request, int $storeProduct): JsonResponse
    {
        $this->reviews->assertProduct($storeProduct);
        $review = $this->reviews->submitForProduct($this->customer($request), $storeProduct, $request->toData());

        return $this->created($request, $review);
    }

    public function storeForItem(StoreReviewRequest $request, string $order, int $item): JsonResponse
    {
        $review = $this->reviews->submitForOrderItem($this->customer($request), $order, $item, $request->toData());

        return $this->created($request, $review);
    }

    private function customer(Request $request): Customer
    {
        return StoreApiCustomer::resolve($request) ?? throw EngagementException::text('reviews.errors.guest', 'guest', 401);
    }

    private function created(Request $request, ProductReview $review): JsonResponse
    {
        $pending = $review->status === ReviewStatus::Pending;

        return JsonResponder::created(__st($pending ? 'reviews.messages.pending' : 'reviews.messages.published'), [
            'review' => (new ReviewResource($review))->toArray($request),
            'summary' => $this->summaries->summary($review->store_product_id),
        ]);
    }
}
