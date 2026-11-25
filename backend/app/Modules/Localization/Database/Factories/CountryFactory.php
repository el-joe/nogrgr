<?php

namespace App\Modules\Localization\Database\Factories;

use App\Modules\Localization\Enums\Continent;
use App\Modules\Localization\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $iso2 = strtoupper(fake()->unique()->lexify('??'));

        return [
            'iso2' => $iso2,
            'iso3' => $iso2.'X',
            'numeric_code' => null,
            'name' => ['en' => 'Country '.$iso2, 'ar' => 'دولة '.$iso2],
            'phone_code' => '999',
            'currency_code' => 'USD',
            'flag' => '',
            'continent' => Continent::Asia,
            'is_active' => true,
        ];
    }

    /**
     * A real country by ISO code with its seeded name, phone code and currency.
     */
    public function iso(string $iso2): static
    {
        $known = [
            'SA' => ['SAU', 'Saudi Arabia', 'المملكة العربية السعودية', '966', 'SAR', Continent::Asia],
            'AE' => ['ARE', 'United Arab Emirates', 'الإمارات العربية المتحدة', '971', 'AED', Continent::Asia],
            'EG' => ['EGY', 'Egypt', 'مصر', '20', 'EGP', Continent::Africa],
            'KW' => ['KWT', 'Kuwait', 'الكويت', '965', 'KWD', Continent::Asia],
            'US' => ['USA', 'United States', 'الولايات المتحدة', '1', 'USD', Continent::NorthAmerica],
            'GB' => ['GBR', 'United Kingdom', 'المملكة المتحدة', '44', 'GBP', Continent::Europe],
        ];
        [$iso3, $en, $ar, $phone, $currency, $continent] = $known[$iso2];

        return $this->state([
            'iso2' => $iso2,
            'iso3' => $iso3,
            'name' => ['en' => $en, 'ar' => $ar],
            'phone_code' => $phone,
            'currency_code' => $currency,
            'flag' => implode('', array_map(static fn (string $letter): string => (string) mb_chr(0x1F1E6 + ord($letter) - 65), str_split($iso2))),
            'continent' => $continent,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
