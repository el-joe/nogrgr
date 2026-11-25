<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ProductLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductLabel>
 */
class ProductLabelFactory extends Factory
{
    protected $model = ProductLabel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'text' => ['en' => 'Delivered by {delivery_date}', 'ar' => 'التوصيل بحلول {delivery_date}'],
            'icon' => 'truck',
            'country_ids' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
