<?php

namespace App\Modules\Ai\Repositories\Translation;

use App\Modules\Ai\Contracts\StoreTranslationSource;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StoreTranslationService;

/**
 * Storefront static texts of the store (tenant_static_texts, decision #45), translated
 * from English. Skipped: keys locked by the platform, rows the vendor locked or edited
 * by hand, and rows already holding a platform (base) value. Item key = text key.
 */
class StaticTextsSource implements StoreTranslationSource
{
    /**
     * @var array<string, array<string, array{english: string|null, value: string|null, source: StaticTextSource|null, is_locked: bool, platform_locked: bool}>>
     */
    private array $rows = [];

    public function __construct(private readonly StoreTranslationService $translations) {}

    public function section(): StoreTranslationSection
    {
        return StoreTranslationSection::StaticTexts;
    }

    public function keys(string $locale): array
    {
        return array_keys(array_filter($this->rows($locale), static fn (array $row): bool => self::eligible($row)));
    }

    public function items(array $keys, string $locale, array $retranslate = []): array
    {
        $rows = $this->rows($locale);
        $items = [];

        foreach ($keys as $key) {
            $row = $rows[$key] ?? null;

            if ($row === null || ! self::eligible($row)) {
                continue;
            }

            $english = (string) $row['english'];
            $filled = trim((string) $row['value']) !== '';
            $translate = ! $filled || isset($retranslate[$key]) ? ['value'] : [];
            $items[$key] = TranslationItemData::make($key, ['value' => [StaticTextCatalog::FALLBACK, $english]], $translate);
        }

        return $items;
    }

    public function write(string $key, string $locale, array $translations): void
    {
        if (isset($translations['value'])) {
            $this->translations->writeAiStaticText($key, $locale, $translations['value']);
        }
    }

    public function models(): array
    {
        return [];
    }

    /**
     * @param  array{english: string|null, value: string|null, source: StaticTextSource|null, is_locked: bool, platform_locked: bool}  $row
     */
    private static function eligible(array $row): bool
    {
        $filled = trim((string) $row['value']) !== '';

        return ! $row['platform_locked']
            && ! $row['is_locked']
            && $row['source']?->isManual() !== true
            && trim((string) $row['english']) !== ''
            && (! $filled || $row['source'] === StaticTextSource::Ai);
    }

    /**
     * @return array<string, array{english: string|null, value: string|null, source: StaticTextSource|null, is_locked: bool, platform_locked: bool}>
     */
    private function rows(string $locale): array
    {
        return $this->rows[$locale] ??= $this->translations->staticRows($locale)->keyBy('key')->all();
    }
}
