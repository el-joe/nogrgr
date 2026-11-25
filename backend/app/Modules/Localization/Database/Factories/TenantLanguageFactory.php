<?php

namespace App\Modules\Localization\Database\Factories;

use App\Modules\Localization\Enums\TenantLanguageSource;
use App\Modules\Localization\Models\TenantLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantLanguage>
 */
class TenantLanguageFactory extends Factory
{
    protected $model = TenantLanguage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'language_code' => fake()->unique()->lexify('??'),
            'is_enabled' => true,
            'is_default' => false,
            'sort_order' => 10,
            'source' => TenantLanguageSource::Free,
            'enabled_at' => now(),
        ];
    }
}
