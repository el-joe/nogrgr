<?php

namespace App\Modules\Localization\Database\Seeders;

use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Shared\Money\Money;
use Illuminate\Database\Seeder;
use JsonException;

/**
 * Central `currencies`: ISO 4217 currencies from Database/Data/currencies.json
 * (names en/ar, symbol, decimals). USD is the base (rate 1, always active); the GCC,
 * Egypt and the major world currencies start active, the rest inactive. Rates come
 * from the SyncExchangeRates job. Idempotent: re-running keeps rates and admin settings.
 *
 *   php artisan db:seed --class="App\Modules\Localization\Database\Seeders\CurrencySeeder"
 */
class CurrencySeeder extends Seeder
{
    public const DATA_FILE = __DIR__.'/../Data/currencies.json';

    /**
     * Active on a fresh install, in display order.
     */
    public const ACTIVE = ['USD', 'SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP', 'EUR', 'GBP'];

    /**
     * @throws JsonException
     */
    public function run(CurrencyRepositoryContract $currencies): void
    {
        /** @var list<array{code: string, name: array<string, string>, symbol: string, decimals: int}> $data */
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        $rows = array_map(static function (array $currency) use ($now): array {
            $position = array_search($currency['code'], self::ACTIVE, true);

            return [
                'code' => $currency['code'],
                'name' => json_encode($currency['name'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'symbol' => $currency['symbol'],
                'decimals' => $currency['decimals'],
                'rate' => '1.000000',
                'is_active' => $position !== false,
                'is_base' => $currency['code'] === Money::BASE_CURRENCY,
                'rate_synced_at' => null,
                'sort_order' => $position !== false ? $position + 1 : 100,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $data);

        foreach (array_chunk($rows, 100) as $chunk) {
            $currencies->upsertMany($chunk);
        }

        $currencies->query()->where('code', Money::BASE_CURRENCY)->update(['is_base' => true, 'is_active' => true, 'rate' => '1.000000']);
    }
}
