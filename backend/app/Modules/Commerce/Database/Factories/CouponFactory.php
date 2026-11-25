<?php

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CPN####??')),
            'name' => null,
            'type' => CouponType::Percentage,
            'percentage' => 10,
            'min_spend_minor' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
            'store_scope' => PromotionScope::AllStores,
        ];
    }

    public function fixed(int $amountMinor): static
    {
        return $this->state(['type' => CouponType::Fixed, 'percentage' => null, 'amount_minor' => $amountMinor]);
    }
}
