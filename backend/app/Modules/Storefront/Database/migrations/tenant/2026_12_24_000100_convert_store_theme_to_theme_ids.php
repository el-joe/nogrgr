<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Decision #119 / #120: the store theme setting stores central theme ids instead of slugs:
|   {"default": "elora-3", "countries": {"191": "souqify-2"}}  →  {"default": 4, "countries": {"191": 9}}
| Slugs the catalogue does not know are dropped (the storefront falls back to the platform
| default). Runs per store; StoreThemeService still reads a leftover slug.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_settings')) {
            return;
        }

        $row = DB::table('store_settings')->where('key', 'store_theme')->first();

        if ($row === null) {
            return;
        }

        $value = json_decode((string) $row->value, true);

        if (! is_array($value)) {
            return;
        }

        $central = DB::connection((string) config('tenancy.database.central_connection'));

        if (! $central->getSchemaBuilder()->hasTable('themes')) {
            return;
        }

        $ids = $central->table('themes')->pluck('id', 'slug')->map(static fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return;
        }

        $convert = static function (mixed $theme) use ($ids): ?int {
            if (is_int($theme) || (is_string($theme) && ctype_digit($theme))) {
                return in_array((int) $theme, $ids, true) ? (int) $theme : null;
            }

            return is_string($theme) ? ($ids[$theme] ?? null) : null;
        };

        $countries = [];

        foreach ((array) ($value['countries'] ?? []) as $countryId => $theme) {
            $id = $convert($theme);

            if ($id !== null && is_numeric($countryId)) {
                $countries[(string) (int) $countryId] = $id;
            }
        }

        DB::table('store_settings')->where('key', 'store_theme')->update([
            'value' => json_encode(['default' => $convert($value['default'] ?? null), 'countries' => (object) $countries]),
        ]);
    }

    public function down(): void
    {
        // Ids keep working (StoreThemeService reads ids and slugs).
    }
};
