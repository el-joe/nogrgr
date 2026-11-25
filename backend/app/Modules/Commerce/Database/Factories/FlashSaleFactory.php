<?php

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\FlashSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSale>
 */
class FlashSaleFactory extends Factory
{
    protected $model = FlashSale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => ['en' => ucfirst($title), 'ar' => 'تخفيض '.$title],
            'discount_percentage' => 20,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(3),
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
}
