<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Central product for tests and the taxonomy screens (the product editor lands in P3 batch 2).
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'sku' => 'NG-'.strtoupper(Str::random(8)),
            'name' => ['en' => $name, 'ar' => 'منتج '.$name],
            'status' => ProductStatus::Published,
            'delivery_scope' => DeliveryScope::cases()[0],
            'assignment_scope' => AssignmentScope::All,
            'base_price' => Money::ofMinor(fake()->numberBetween(500, 50000)),
            'sale_price' => null,
            'stock' => 10,
            'sold_count' => 0,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    /**
     * Attach to a category at the given position (category_product.sort_order).
     */
    public function inCategory(Category $category, int $position = 0): static
    {
        return $this->afterCreating(fn (Product $product) => $product->categories()->attach($category->id, ['sort_order' => $position]));
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft, 'published_at' => null]);
    }
}
