<?php

namespace App\Modules\Localization\Services;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Usage\AiUsageRecorder;
use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Enums\StoreContentType;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Jobs\TranslateMissingStaticTexts;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantStaticTextRepositoryContract;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;
use App\Modules\Localization\Services\StaticTexts\TenantStaticTextService;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Vendor › Settings › Store translations (VEN-075, decisions #44 / #45), current store
 * (tenancy initialized): storefront static texts per language (edit = manual + locked,
 * reset), store-owned content fields per language (edit = locked for the AI run, unlock),
 * "Translate" suggestions for one field and "Translate missing with AI" for a language —
 * both through the custom translation provider (FreeLLMAPI by default).
 */
class StoreTranslationService
{
    private const CHUNK = 40;

    public function __construct(
        private readonly TenantStaticTextService $texts,
        private readonly TenantStaticTextRepositoryContract $rows,
        private readonly StaticTextCatalog $catalog,
        private readonly StaticTextResolver $resolver,
        private readonly TenantLanguageRepositoryContract $tenantLanguages,
        private readonly LanguageRepositoryContract $languages,
        private readonly ContentTranslationLockRepositoryContract $locks,
    ) {}

    /**
     * The store's languages (code => name), ar / en first.
     *
     * @return array<string, string>
     */
    public function locales(): array
    {
        $this->ensureTenancy();
        $codes = array_values(array_unique([...Language::CORE, ...$this->tenantLanguages->codes()]));
        $names = $this->languages->query()->whereIn('code', $codes)->get()->mapWithKeys(fn (Language $language): array => [$language->code => (string) $language->name]);

        return collect($codes)->mapWithKeys(fn (string $code): array => [$code => $names->get($code, strtoupper($code))])->all();
    }

    /**
     * Language picked by default: the first one that is not English.
     */
    public function defaultLocale(): string
    {
        return collect(array_keys($this->locales()))->first(fn (string $code): bool => $code !== StaticTextCatalog::FALLBACK) ?? StaticTextCatalog::FALLBACK;
    }

    public function isStoreLocale(string $locale): bool
    {
        return array_key_exists($locale, $this->locales());
    }

    /**
     * Static text rows of one language, filtered by tab (all | missing | locked | edited)
     * and search.
     *
     * @return Collection<int, array{key: string, value: string|null, base: string|null, english: string|null, source: StaticTextSource|null, is_locked: bool, platform_locked: bool}>
     */
    public function staticRows(string $locale, string $tab = 'all', ?string $search = null): Collection
    {
        $english = $this->rows->valuesFor(StaticTextCatalog::FALLBACK);
        $rows = $this->texts->list($locale, $search)->map(fn (array $row): array => [
            'key' => $row['key'],
            'value' => $row['value'],
            'base' => $row['base'],
            'english' => $english[$row['key']] ?? $this->catalog->value($row['key'], StaticTextCatalog::FALLBACK),
            'source' => $row['source'],
            'is_locked' => $row['is_locked'],
            'platform_locked' => $row['platform_locked'],
        ]);

        return $tab === 'all' ? $rows : $rows->filter(fn (array $row): bool => self::inTab($row, $tab))->values();
    }

    /**
     * @return array{all: int, missing: int, locked: int}
     */
    public function staticCounts(string $locale): array
    {
        $rows = $this->staticRows($locale);

        return [
            'all' => $rows->count(),
            'missing' => $rows->filter(fn (array $row): bool => self::inTab($row, 'missing'))->count(),
            'locked' => $rows->filter(fn (array $row): bool => self::inTab($row, 'locked'))->count(),
        ];
    }

    /**
     * @param  array{value: string|null, source: StaticTextSource|null, is_locked: bool, platform_locked: bool}  $row
     */
    private static function inTab(array $row, string $tab): bool
    {
        return match ($tab) {
            'missing' => $row['value'] === null || $row['value'] === '',
            'locked' => $row['platform_locked'],
            'edited' => $row['is_locked'] || $row['source']?->isManual() === true,
            default => true,
        };
    }

    public function updateStatic(string $key, string $locale, string $value): void
    {
        $this->texts->update($key, $locale, $value);
    }

    public function resetStatic(string $key, string $locale): void
    {
        $this->texts->reset($key, $locale);
    }

    /**
     * Suggest a translation of one static text (not saved).
     */
    public function suggestStatic(string $key, string $locale): string
    {
        if (! $this->catalog->has($key)) {
            throw TranslationRuleException::unknownKey($key);
        }

        $source = $this->rows->valuesFor(StaticTextCatalog::FALLBACK)[$key] ?? $this->catalog->value($key, StaticTextCatalog::FALLBACK) ?? '';

        return $this->translateOne($source, StaticTextCatalog::FALLBACK, $locale);
    }

    public function queueMissing(Tenant $tenant, string $locale): void
    {
        TranslateMissingStaticTexts::dispatch((int) $tenant->id, $locale);
    }

    /**
     * Job body: translate every empty static text of $locale from English, in chunks.
     * Rows written are marked "ai" and stay unlocked; protected rows are skipped.
     *
     * @return int rows written
     */
    public function fillMissingStaticTexts(string $locale): int
    {
        $missing = $this->staticRows($locale, 'missing')
            ->reject(fn (array $row): bool => $row['platform_locked'] || $row['is_locked'] || $row['english'] === null || $row['english'] === '');
        $written = 0;

        foreach ($missing->chunk(self::CHUNK) as $chunk) {
            $sources = $chunk->mapWithKeys(fn (array $row): array => [$row['key'] => (string) $row['english']])->all();
            $translated = $this->client()->translate($sources, StaticTextCatalog::FALLBACK, $locale);

            foreach ($translated as $key => $value) {
                $key = (string) $key;

                if (! is_string($value) || ! isset($sources[$key])) {
                    continue;
                }

                $written += $this->writeAiStaticText($key, $locale, $value) ? 1 : 0;
            }
        }

        $this->resolver->forget($locale);

        return $written;
    }

    /**
     * Save an AI translation of one static text (source "ai", unlocked) unless the row is
     * protected (locked / manual edit). Callers flush the resolver cache when done.
     */
    public function writeAiStaticText(string $key, string $locale, string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        $row = $this->rows->findEntry($key, $locale);

        if ($row !== null && $row->isProtected()) {
            return false;
        }

        $attributes = ['value' => $value, 'source' => StaticTextSource::Ai, 'is_locked' => false, 'source_hash' => $this->catalog->hash($key, $locale)];
        $row === null ? $this->rows->create($attributes + ['key' => $key, 'locale' => $locale]) : $this->rows->update($row, $attributes);

        return true;
    }

    public function forgetStaticTexts(string $locale): void
    {
        $this->resolver->forget($locale);
    }

    /**
     * Records of one content type for the content table.
     *
     * @return Builder<Model&AiTranslatable>
     */
    public function contentQuery(StoreContentType $type, ?string $search = null): Builder
    {
        $this->ensureTenancy();
        $query = $type->query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';
            $query->whereRaw('lower('.$type->titleField().') like ?', [$term]);
        }

        return $query;
    }

    /**
     * "id|field" keys of hand-translated fields of a type in one locale.
     *
     * @return array<string, true>
     */
    public function lockedContent(StoreContentType $type, string $locale): array
    {
        return $this->locks->lockedKeys($type->value, $locale);
    }

    /**
     * Save one field of one record in one locale and lock it from the AI run.
     */
    public function updateContent(StoreContentType $type, int $id, string $field, string $locale, string $value): void
    {
        $record = $this->contentRecord($type, $id, $field);
        $record->setTranslation($field, $locale, $value);
        $record->save();
        $this->locks->lock($type->value, $id, $field, $locale);
    }

    public function unlockContent(StoreContentType $type, int $id, string $field, string $locale): void
    {
        $this->contentRecord($type, $id, $field);
        $this->locks->unlock($type->value, $id, $field, $locale);
    }

    public function suggestContent(StoreContentType $type, int $id, string $field, string $locale): string
    {
        $record = $this->contentRecord($type, $id, $field);
        $english = (string) $record->getTranslation($field, StaticTextCatalog::FALLBACK, false);

        return $english !== ''
            ? $this->translateOne($english, StaticTextCatalog::FALLBACK, $locale)
            : $this->translateOne((string) $record->getTranslation($field, 'ar', false), 'ar', $locale);
    }

    /**
     * @return Model&AiTranslatable
     */
    private function contentRecord(StoreContentType $type, int $id, string $field): Model
    {
        $this->ensureTenancy();

        if (! in_array($field, $type->fields(), true)) {
            throw TranslationRuleException::unknownContent();
        }

        return $type->query()->whereKey($id)->first() ?? throw TranslationRuleException::unknownContent();
    }

    private function translateOne(string $source, string $from, string $to): string
    {
        if (trim($source) === '' || $from === $to) {
            return $source;
        }

        try {
            $client = $this->client();
            $result = app(AiUsageRecorder::class)->measure($client, new AiUsageContext(AiFeature::StoreTextSuggestion), static fn (): array => $client->translate(['text' => $source], $from, $to));
        } catch (AiRequestException) {
            throw TranslationRuleException::translationUnavailable();
        }

        return (string) ($result['text'] ?? '');
    }

    private function client(): TranslationClient
    {
        return app(TranslationClient::class);
    }

    private function ensureTenancy(): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Tenancy must be initialized to manage store translations.');
        }
    }
}
