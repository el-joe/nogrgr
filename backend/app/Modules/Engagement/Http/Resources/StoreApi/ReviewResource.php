<?php

namespace App\Modules\Engagement\Http\Resources\StoreApi;

use App\Modules\Engagement\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One public product review (SF-ECO-019a): author label, rating, title, body, dates.
 * Every review comes from a delivered order line, hence verified_purchase.
 *
 * @mixin ProductReview
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductReview $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'author' => $review->author_name,
            'avatar_url' => null,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->comment,
            'status' => $review->status->value,
            'created_at' => $review->created_at?->toIso8601String(),
            'created_human' => $review->created_at?->locale(app()->getLocale())->diffForHumans(),
            'verified_purchase' => true,
            'images' => [],
        ];
    }
}
