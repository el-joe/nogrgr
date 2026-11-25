<?php

namespace App\Modules\Localization\Database\Seeders;

use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use Illuminate\Database\Seeder;
use JsonException;

/**
 * Central `countries`: every ISO 3166-1 country from Database/Data/countries.json
 * (names en/ar, ISO codes, phone code, default currency, flag, continent).
 * Idempotent: re-running updates the data but keeps `is_active` as the admin set it.
 *
 *   php artisan db:seed --class="App\Modules\Localization\Database\Seeders\CountrySeeder"
 */
class CountrySeeder extends Seeder
{
    public const DATA_FILE = __DIR__.'/../Data/countries.json';

    /**
     * @throws JsonException
     */
    public function run(CountryRepositoryContract $countries): void
    {
        /** @var list<array{iso2: string, iso3: string, numeric_code: string|null, name: array<string, string>, phone_code: string|null, currency_code: string|null, flag: string, continent: string|null, is_active: bool}> $data */
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        $rows = array_map(static fn (array $country): array => [
            ...$country,
            'name' => json_encode($country['name'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ], $data);

        foreach (array_chunk($rows, 100) as $chunk) {
            $countries->upsertMany($chunk);
        }
    }
}
