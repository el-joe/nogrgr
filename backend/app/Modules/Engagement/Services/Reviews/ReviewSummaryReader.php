<?php

namespace App\Modules\Engagement\Services\Reviews;

use App\Modules\Engagement\DTOs\ReviewSummaryData;
use App\Modules\Engagement\Repositories\Contracts\ProductReviewRepositoryContract;

/**
 * Review summaries for the product page, product cards, listings, top-rated ranking and
 * JSON-LD — approved reviews only, many products in one query (tenant context).
 */
class ReviewSummaryReader
{
    public function __construct(private readonly ProductReviewRepositoryContract $reviews) {}

    /**
     * @param  list<int>  $storeProductIds
     * @return array<int, array{average: float, count: int}> missing id = no reviews
     */
    public function summaries(array $storeProductIds): array
    {
        $result = [];

        foreach ($this->reviews->histograms($storeProductIds) as $id => $histogram) {
            $summary = ReviewSummaryData::fromHistogram($histogram);

            if ($summary->count > 0) {
                $result[$id] = ['average' => $summary->average, 'count' => $summary->count];
            }
        }

        return $result;
    }

    /**
     * @return array{average: float, count: int, distribution: array<int, int>}
     */
    public function summary(int $storeProductId): array
    {
        return $this->data($storeProductId)->toArray();
    }

    public function data(int $storeProductId): ReviewSummaryData
    {
        return ReviewSummaryData::fromHistogram($this->reviews->histograms([$storeProductId])[$storeProductId] ?? []);
    }

    /**
     * @return list<int> store product ids by average desc, count desc
     */
    public function topRatedIds(int $limit, int $minCount = 1): array
    {
        return $this->reviews->topRatedIds($limit, $minCount);
    }
}
