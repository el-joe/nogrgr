<?php

namespace App\Modules\Shipping\Database\Factories;

use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingCompany>
 */
class ShippingCompanyFactory extends Factory
{
    protected $model = ShippingCompany::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'contact_email' => fake()->unique()->safeEmail(),
            'tracking_url_pattern' => 'https://track.example.com/{tracking}',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
