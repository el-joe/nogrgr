<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\StoreCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Store-own category (tenant DB). Create inside an initialized tenant.
 *
 * @extends Factory<StoreCategory>
 */
class StoreCategoryFactory extends Factory
{
    protected $model = StoreCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => ['en' => $name, 'ar' => 'قسم '.$name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
