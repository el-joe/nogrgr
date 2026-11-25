<?php

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Models\StoreFlashSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreFlashSale>
 */
class StoreFlashSaleFactory extends Factory
{
    protected $model = StoreFlashSale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => ['en' => ucfirst($title), 'ar' => 'عرض '.$title],
            'discount_percentage' => 15,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ];
    }
}
