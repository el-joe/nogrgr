<?php

namespace App\Modules\Localization\Services\StaticTexts;

use App\Modules\Localization\DTOs\StaticTextSyncResult;
use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Events\StaticTextsNeedTranslation;
use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantStaticTextRepositoryContract;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Copies the base static texts (ar + en) into the current store at provisioning, and
 * after deploys / admin edits inserts the keys a store is missing and refreshes rows
 * that still hold an older base value. Manually edited or locked rows are never
 * touched. New or changed keys of a store with purchased languages fire
 * StaticTextsNeedTranslation for the P10 AI run.
 */
class StaticTextSyncService
{
    public function __construct(
        private readonly TenantStaticTextRepositoryContract $texts,
        private readonly TenantLanguageRepositoryContract $languages,
        private readonly StaticTextCatalog $catalog,
        private readonly StaticTextResolver $resolver,
    ) {}

    public function syncCurrentTenant(): StaticTextSyncResult
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Tenancy must be initialized to sync static texts.');
        }

        $inserted = 0;
        $updated = 0;
        $newKeys = [];
        $changedKeys = [];

        DB::connection((new TenantStaticText)->getConnectionName())->transaction(function () use (&$inserted, &$updated, &$newKeys, &$changedKeys): void {
            foreach (StaticTextCatalog::BASE_LOCALES as $locale) {
                $existing = $this->texts->keyedFor($locale);
                $rows = [];
                $now = now();

                foreach ($this->catalog->lines($locale) as $key => $value) {
                    $hash = sha1($value);
                    /** @var TenantStaticText|null $row */
                    $row = $existing->get($key);

                    if ($row === null) {
                        $rows[] = [
                            'key' => $key,
                            'locale' => $locale,
                            'value' => $value,
                            'source_hash' => $hash,
                            'source' => StaticTextSource::Base->value,
                            'is_locked' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $newKeys[$key] = true;

                        continue;
                    }

                    if ($row->source_hash === $hash) {
                        continue;
                    }

                    if ($locale === StaticTextCatalog::FALLBACK) {
                        $changedKeys[$key] = true;
                    }

                    if ($row->isProtected()) {
                        // Keep the vendor's value; remember the new base so the change is reported once.
                        $this->texts->update($row, ['source_hash' => $hash]);

                        continue;
                    }

                    $this->texts->update($row, ['value' => $value, 'source_hash' => $hash, 'source' => StaticTextSource::Base]);
                    $updated++;
                }

                $this->texts->insertMany($rows);
                $inserted += count($rows);
            }
        });

        $this->resolver->forget();
        $locales = $this->translationLocales();
        $result = new StaticTextSyncResult(
            $inserted,
            $updated,
            array_map('strval', array_keys($newKeys)),
            array_values(array_diff(array_map('strval', array_keys($changedKeys)), array_map('strval', array_keys($newKeys)))),
            $locales,
        );

        if ($locales !== [] && $result->keysToTranslate() !== []) {
            event(new StaticTextsNeedTranslation(tenant()?->getTenantKey() ?? 0, $result->keysToTranslate(), $locales));
        }

        return $result;
    }

    /**
     * Enabled store languages beyond the free ar / en.
     *
     * @return list<string>
     */
    private function translationLocales(): array
    {
        return array_values(array_diff(
            $this->languages->query()->where('is_enabled', true)->orderBy('sort_order')->pluck('language_code')->map(fn (mixed $code): string => (string) $code)->all(),
            StaticTextCatalog::BASE_LOCALES,
        ));
    }
}
