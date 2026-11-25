<?php

namespace App\Modules\Shipping\Database\Factories;

use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Modules\Shipping\Models\ShippingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRule>
 */
class ShippingRuleFactory extends Factory
{
    protected $model = ShippingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' standard',
            'status' => ShippingRuleStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ShippingRuleStatus::Draft]);
    }
}
