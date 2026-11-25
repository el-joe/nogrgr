<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Store product (tenant DB): own product by default, `central($id)` for an imported one.
 * Create inside an initialized tenant.
 *
 * @extends Factory<StoreProduct>
 */
class StoreProductFactory extends Factory
{
    protected $model = StoreProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'source' => StoreProductSource::Own,
            'name' => ['en' => $name, 'ar' => 'منتج '.$name],
            'summary' => ['en' => 'Short summary', 'ar' => 'وصف مختصر'],
            'description' => ['en' => 'Description', 'ar' => 'الوصف'],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'sku' => 'OWN-'.strtoupper(Str::random(6)),
            'is_active' => true,
            'price' => Money::ofMinor(2500),
            'stock' => 10,
            'manage_stock' => true,
            'imported_at' => null,
        ];
    }

    public function central(int $centralProductId): static
    {
        return $this->state([
            'source' => StoreProductSource::Central,
            'central_product_id' => $centralProductId,
            'name' => null,
            'summary' => null,
            'description' => null,
            'slug' => null,
            'sku' => null,
            'price' => null,
            'stock' => null,
            'imported_at' => now(),
        ]);
    }
}
