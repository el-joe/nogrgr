<?php

namespace App\Modules\Engagement\Events;

use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Models\ProductReview;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A review changed state through moderation (approved, rejected, hidden). Product
 * summaries / caches of $review->store_product_id are stale.
 */
final class ReviewModerated
{
    use Dispatchable;

    public function __construct(public readonly ProductReview $review, public readonly ReviewStatus $from) {}
}
