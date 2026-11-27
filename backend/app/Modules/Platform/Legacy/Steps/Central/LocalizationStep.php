<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyTranslations;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Str;

/**
 * Languages, currencies, countries and cities. The new DB already ships ISO reference
 * data, so old rows are matched by natural key (code / iso2 / country + slug) and only
 * unknown ones are inserted. Old store-facing flags (language price, active) are kept.
 */
class LocalizationStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Localization;
    }

    public function run(LegacyContext $context): void
    {
        $this->languages($context);
        $this->currencies($context);
        $this->countries($context);
        $this->cities($context);
    }

    private function languages(LegacyContext $context): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('languages')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'languages');
            $code = strtolower((string) $row->code);

            $business = [
                'is_active' => (bool) $row->is_active,
                'price_minor' => (bool) $row->is_free ? 0 : LegacyValues::minor($row->price),
            ];

            if ($writer->connection()->table('languages')->where('code', $code)->exists()) {
                // Reference data (names, direction) stays the new seed's; only store-facing flags move.
                $writer->upsert('language', (int) $row->id, 'languages', $business, ['code' => $code]);

                continue;
            }

            $writer->upsert('language', (int) $row->id, 'languages', $business + [
                'code' => $code,
                'name' => ['en' => (string) $row->name],
                'native_name' => Str::limit((string) $row->native_name, 100, ''),
                'direction' => in_array($row->direction, ['rtl', 'ltr'], true) ? $row->direction : 'ltr',
                'sort_order' => (int) $row->sort_order,
            ]);
        }
    }

    private function currencies(LegacyContext $context): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('currencies')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'currencies');
            $code = strtoupper(trim((string) $row->code));

            if (strlen($code) !== 3) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'currencies', (int) $row->id, 'Currency without a 3-letter code skipped');
                $context->report->skipped('central', 'currencies');

                continue;
            }

            $existing = $writer->connection()->table('currencies')->where('code', $code)->value('id');

            if ($existing !== null) {
                // Rates are synced by the new rate job; only the mapping is needed.
                $writer->link('currency', (int) $row->id, (int) $existing);

                continue;
            }

            $writer->upsert('currency', (int) $row->id, 'currencies', [
                'code' => $code,
                'name' => ['en' => (string) $row->name],
                'symbol' => Str::limit((string) ($row->sign ?? $code), 16, ''),
                'decimals' => 2,
                'rate' => (string) $row->conversion_rate,
                'is_active' => true,
                'is_base' => $code === 'USD',
                'sort_order' => 0,
            ]);
        }
    }

    private function countries(LegacyContext $context): void
    {
        $writer = $context->central;
        $rows = $context->source->central()->table('countries')->orderBy('id')->get();
        $names = $context->translations()->load('App\\Models\\Country', $rows->pluck('id')->map('intval')->all());

        foreach ($rows as $row) {
            $context->report->read('central', 'countries');
            $iso2 = strtoupper((string) $row->iso2);

            if (strlen($iso2) !== 2) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'countries', (int) $row->id, 'Country without ISO2 skipped');
                $context->report->skipped('central', 'countries');

                continue;
            }

            $existing = $writer->connection()->table('countries')->where('iso2', $iso2)->value('id');

            if ($existing !== null) {
                $writer->link('country', (int) $row->id, (int) $existing);

                continue;
            }

            $writer->upsert('country', (int) $row->id, 'countries', [
                'iso2' => $iso2,
                'iso3' => strtoupper(Str::limit((string) ($row->iso3 ?? $iso2), 3, '')),
                'name' => LegacyTranslations::field($names, (int) $row->id, 'name', (string) $row->name) ?? ['en' => $iso2],
                'phone_code' => $row->phone_code !== null ? Str::limit((string) $row->phone_code, 8, '') : null,
                'currency_code' => $row->currency_code !== null && strlen((string) $row->currency_code) === 3 ? strtoupper((string) $row->currency_code) : null,
                'flag' => (string) ($row->flag_emoji ?? ''),
                'is_active' => (bool) $row->is_active_for_tenants,
            ]);
        }
    }

    private function cities(LegacyContext $context): void
    {
        $writer = $context->central;

        $context->source->central()->table('cities')->orderBy('id')->chunkById(500, function ($rows) use ($context, $writer): void {
            $writer->transaction(function () use ($rows, $context, $writer): void {
                foreach ($rows as $row) {
                    $context->report->read('central', 'cities');
                    $countryId = $writer->id('country', (int) $row->country_id);

                    if ($countryId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'cities', (int) $row->id, 'City of an unknown country skipped (orphan)');
                        $context->report->skipped('central', 'cities');

                        continue;
                    }

                    $slug = Str::limit(Str::slug((string) $row->name) ?: 'city-'.$row->id, 120, '');
                    $existing = $writer->connection()->table('cities')->where(['country_id' => $countryId, 'slug' => $slug])->value('id');

                    if ($existing !== null) {
                        $writer->link('city', (int) $row->id, (int) $existing);

                        continue;
                    }

                    $writer->upsert('city', (int) $row->id, 'cities', [
                        'country_id' => $countryId,
                        'slug' => $slug,
                        'name' => ['en' => (string) $row->name],
                        'type' => 'city',
                        'is_active' => true,
                    ]);
                }
            });
        });
    }
}
