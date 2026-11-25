<?php

namespace App\Modules\Localization\Database\Factories;

use App\Modules\Localization\Enums\TextDirection;
use App\Modules\Localization\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->lexify('??');

        return [
            'code' => $code,
            'name' => ['en' => 'Language '.$code, 'ar' => 'لغة '.$code],
            'native_name' => 'Native '.$code,
            'direction' => TextDirection::Ltr,
            'flag' => null,
            'is_active' => true,
            'is_free' => true,
            'is_default' => false,
            'price_minor' => 0,
            'ai_translation_price_minor' => 0,
            'offers_ai_translation' => false,
            'country_ids' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * A real language by code (en, ar, fr …).
     */
    public function code(string $code, string $name = '', TextDirection $direction = TextDirection::Ltr): static
    {
        return $this->state(fn (): array => [
            'code' => $code,
            'name' => ['en' => $name !== '' ? $name : strtoupper($code), 'ar' => $name !== '' ? $name : strtoupper($code)],
            'native_name' => $name !== '' ? $name : strtoupper($code),
            'direction' => $direction,
        ]);
    }

    public function paid(int $priceMinor = 1500): static
    {
        return $this->state(fn (): array => ['is_free' => false, 'price_minor' => $priceMinor]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
