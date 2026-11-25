<?php

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Models\StoreCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreCoupon>
 */
class StoreCouponFactory extends Factory
{
    protected $model = StoreCoupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('STR####??')),
            'type' => CouponType::Percentage,
            'percentage' => 10,
            'min_spend_minor' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }
}
