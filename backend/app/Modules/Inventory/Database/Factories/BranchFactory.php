<?php

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Models\Branch;
use App\Modules\Localization\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = Str::title(fake()->unique()->city());

        return [
            'name' => ['en' => $city.' WH', 'ar' => 'مستودع '.$city],
            'code' => strtoupper(Str::random(3)).'-'.fake()->unique()->numberBetween(10, 9999),
            'country_id' => Country::factory(),
            'city_id' => null,
            'address' => fake()->streetAddress(),
            'phone' => null,
            'pickup_hours' => null,
            'allows_pickup' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
