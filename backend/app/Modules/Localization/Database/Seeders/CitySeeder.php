<?php

namespace App\Modules\Localization\Database\Seeders;

use App\Modules\Localization\Enums\CityType;
use App\Modules\Localization\Repositories\Contracts\CityRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use JsonException;

/**
 * Central `cities`: major cities per country from Database/Data/cities.json
 * (`{"SA": [{"en": "Riyadh", "ar": "الرياض", "type": "capital"}, …]}`). Runs CountrySeeder first.
 *
 *   php artisan db:seed --class="App\Modules\Localization\Database\Seeders\CitySeeder"
 */
class CitySeeder extends Seeder
{
    public const DATA_FILE = __DIR__.'/../Data/cities.json';

    /**
     * @throws JsonException
     */
    public function run(CountryRepositoryContract $countries, CityRepositoryContract $cities): void
    {
        $ids = $countries->idsByIso2();

        if ($ids === []) {
            $this->call(CountrySeeder::class);
            $ids = $countries->idsByIso2();
        }

        /** @var array<string, list<array{en: string, ar: string, type?: string}>> $data */
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true, flags: JSON_THROW_ON_ERROR);
        $now = now();
        $rows = [];

        foreach ($data as $iso2 => $list) {
            if (! isset($ids[$iso2])) {
                continue;
            }

            foreach ($list as $city) {
                $rows[] = [
                    'country_id' => $ids[$iso2],
                    'slug' => Str::slug($city['en']),
                    'name' => json_encode(['en' => $city['en'], 'ar' => $city['ar']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'type' => $city['type'] ?? CityType::City->value,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            $cities->upsertMany($chunk);
        }
    }
}
