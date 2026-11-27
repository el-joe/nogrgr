<?php

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\StockAlertSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAlertSubscription>
 */
class StockAlertSubscriptionFactory extends Factory
{
    protected $model = StockAlertSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_product_id' => fake()->numberBetween(1, 1000),
            'customer_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'locale' => 'en',
            'notified_at' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state(fn (): array => ['notified_at' => now()]);
    }
}
