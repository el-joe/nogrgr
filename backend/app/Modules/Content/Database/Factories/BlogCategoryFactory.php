<?php

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Models\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'growth-'.Str::lower(Str::random(8)),
            'name' => ['en' => 'Growth tips', 'ar' => 'نصائح النمو'],
            'description' => ['en' => 'Ideas to grow your store', 'ar' => 'أفكار لتنمية متجرك'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
