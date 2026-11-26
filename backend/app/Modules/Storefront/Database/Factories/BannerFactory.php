<?php

namespace App\Modules\Storefront\Database\Factories;

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(3, true));

        return [
            'placement' => BannerPlacement::HomeHero,
            'title' => ['en' => $title, 'ar' => 'عرض '.$title],
            'image_path' => 'storefront/banners/'.fake()->uuid().'.webp',
            'image_width' => 1440,
            'image_height' => 280,
            'link_url' => '/offers',
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
            'store_scope' => PromotionScope::AllStores,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(5)]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
