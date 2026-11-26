<?php

namespace App\Modules\Engagement\Events;

use App\Modules\Engagement\Models\ProductReview;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer posted a product review (published or waiting for approval).
 */
final class ReviewSubmitted
{
    use Dispatchable;

    public function __construct(public readonly ProductReview $review, public readonly int $tenantId) {}
}
