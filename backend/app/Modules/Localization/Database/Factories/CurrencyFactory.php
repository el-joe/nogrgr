<?php

namespace App\Modules\Localization\Database\Factories;

use App\Modules\Localization\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => ['en' => 'Currency '.$code, 'ar' => 'عملة '.$code],
            'symbol' => $code,
            'decimals' => 2,
            'rate' => '1.000000',
            'is_active' => true,
            'is_base' => false,
            'rate_synced_at' => null,
            'sort_order' => 0,
        ];
    }

    public function code(string $code, string $rate = '1.000000'): static
    {
        return $this->state(fn (): array => ['code' => $code, 'symbol' => $code, 'rate' => $rate]);
    }

    public function base(): static
    {
        return $this->state(fn (): array => ['code' => 'USD', 'symbol' => '$', 'rate' => '1.000000', 'is_base' => true]);
    }
}
