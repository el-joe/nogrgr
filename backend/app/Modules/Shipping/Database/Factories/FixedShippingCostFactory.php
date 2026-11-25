<?php

namespace App\Modules\Shipping\Database\Factories;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\FixedShippingCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedShippingCost>
 */
class FixedShippingCostFactory extends Factory
{
    protected $model = FixedShippingCost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'price_per_gram' => '0.004000',
        ];
    }
}
