<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => ['en' => $name, 'ar' => 'قسم '.$name],
            'description' => null,
            'status' => RecordStatus::Active,
            'is_featured' => false,
            'show_in_menu' => true,
            'sort_order' => 0,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => RecordStatus::Inactive]);
    }
}
