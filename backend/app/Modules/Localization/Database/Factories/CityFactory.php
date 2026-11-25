<?php

namespace App\Modules\Localization\Database\Factories;

use App\Modules\Localization\Enums\CityType;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'country_id' => Country::factory(),
            'slug' => Str::slug($name),
            'name' => ['en' => $name, 'ar' => $name],
            'type' => CityType::City,
            'is_active' => true,
        ];
    }

    /**
     * @param  array{en: string, ar: string}  $name
     */
    public function named(array $name): static
    {
        return $this->state(['slug' => Str::slug($name['en']), 'name' => $name]);
    }
}
