<?php

namespace App\Modules\Engagement\Database\Factories;

use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Models\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A review needs its order line: pass store_product_id, customer_id, order_id and
 * order_item_id (tenant DB).
 *
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(4),
            'comment' => fake()->paragraph(),
            'author_name' => fake()->firstName().' '.strtoupper(fake()->randomLetter()).'.',
            'locale' => 'en',
            'status' => ReviewStatus::Approved,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => ReviewStatus::Pending]);
    }
}
