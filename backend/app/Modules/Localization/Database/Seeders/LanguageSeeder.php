<?php

namespace App\Modules\Localization\Database\Seeders;

use App\Modules\Localization\Enums\TextDirection;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use Illuminate\Database\Seeder;
use JsonException;

/**
 * Central `languages`: every ISO 639-1 language (+ Central Kurdish `ckb`) from
 * Database/Data/languages.json — names en/ar, native name, direction, flag.
 * New rows start inactive and unpriced (the admin sets one USD price); ar + en are free and
 * active (en is the default).
 * Idempotent: re-running refreshes reference data only, never the admin's settings.
 *
 *   php artisan db:seed --class="App\Modules\Localization\Database\Seeders\LanguageSeeder"
 */
class LanguageSeeder extends Seeder
{
    public const DATA_FILE = __DIR__.'/../Data/languages.json';

    /**
     * Right-to-left scripts (forced even if the data file says otherwise).
     */
    public const RTL = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ckb', 'yi', 'dv', 'ug'];

    /**
     * @throws JsonException
     */
    public function run(LanguageRepositoryContract $languages): void
    {
        /** @var list<array{code: string, name: array<string, string>, native_name: string, direction: string, flag: string|null}> $data */
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        $rows = array_map(static function (array $language) use ($now): array {
            $core = in_array($language['code'], Language::CORE, true);

            return [
                'code' => $language['code'],
                'name' => json_encode($language['name'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'native_name' => $language['native_name'],
                'direction' => in_array($language['code'], self::RTL, true) ? TextDirection::Rtl->value : TextDirection::Ltr->value,
                'flag' => $language['flag'],
                'is_active' => $core,
                'is_default' => $language['code'] === Language::FALLBACK,
                'price_minor' => 0,
                'country_ids' => null,
                'sort_order' => $core ? array_search($language['code'], Language::CORE, true) + 1 : 100,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $data);

        foreach (array_chunk($rows, 100) as $chunk) {
            $languages->upsertMany($chunk);
        }

        /* ar + en are always free and active, whatever was edited. */
        $languages->query()->whereIn('code', Language::CORE)->update(['is_active' => true, 'price_minor' => 0]);
    }
}
