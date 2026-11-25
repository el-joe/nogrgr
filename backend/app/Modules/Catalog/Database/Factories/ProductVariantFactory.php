<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => null,
            'price' => null,
            'stock' => 5,
            'position' => 0,
            'status' => RecordStatus::Active,
        ];
    }
}
