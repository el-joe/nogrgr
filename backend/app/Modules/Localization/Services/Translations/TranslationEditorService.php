<?php

namespace App\Modules\Localization\Services\Translations;

use App\Modules\Localization\DTOs\TranslationFileData;
use App\Modules\Localization\DTOs\TranslationValuesData;
use App\Modules\Localization\Enums\TranslationKeyStatus;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StaticTexts\StaticTextDispatcher;
use App\Modules\Localization\Support\OverridingTranslationLoader;
use App\Modules\Localization\Support\TranslationFile;
use App\Shared\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Translation\Translator;

/**
 * Admin translation editor (ADM-092): rows of a file in a language (missing keys,
 * admin overrides), save / add key / create file / revert, and export. Edits are stored
 * in translation_overrides (never on disk, so deploys keep them); storefront base set
 * edits are then synced into every store. Locked keys (ADM-093) cannot be saved.
 */
class TranslationEditorService extends BaseService
{
    public function __construct(
        private readonly TranslationFileRegistry $registry,
        private readonly TranslationOverrideRepositoryContract $overrides,
        private readonly TranslationKeyLockService $locks,
        private readonly LanguageRepositoryContract $languages,
        private readonly StaticTextCatalog $catalog,
        private readonly StaticTextDispatcher $dispatcher,
        private readonly Translator $translator,
    ) {}

    /**
     * Languages the editor offers: en + ar first, then other active languages.
     *
     * @return array<string, string> code => name
     */
    public function locales(): array
    {
        $options = [];

        foreach (Language::CORE as $code) {
            $options[$code] = '';
        }

        foreach ($this->languages->active() as $language) {
            $options[$language->code] = $language->name;
        }

        foreach ($options as $code => $name) {
            if ($name === '') {
                $options[$code] = strtoupper($code);
            }
        }

        $options = ['en' => $options['en'] ?? 'EN', 'ar' => $options['ar'] ?? 'AR'] + $options;

        return $options;
    }

    public function resolveLocale(?string $locale): string
    {
        return $locale !== null && array_key_exists($locale, $this->locales()) ? $locale : 'ar';
    }

    public function findFile(string $id): TranslationFile
    {
        return $this->registry->find($id) ?? throw TranslationRuleException::unknownFile();
    }

    /**
     * Every key of the file with its English source and its value in $locale.
     *
     * @return Collection<int, array{key: string, source: string|null, value: string|null, status: TranslationKeyStatus, locked: bool}>
     */
    public function rows(TranslationFile $file, string $locale): Collection
    {
        $overrides = $this->overrides->mapForFile($file->id);
        $source = array_replace($this->registry->fileLines($file, StaticTextCatalog::FALLBACK), $overrides[StaticTextCatalog::FALLBACK] ?? []);
        $diskValues = $this->registry->fileLines($file, $locale);
        $values = array_replace($diskValues, $overrides[$locale] ?? []);
        $locked = $this->locks->lockedKeys($file->id);

        $keys = array_keys($source + $values + $this->registry->fileLines($file, 'ar') + ($overrides['ar'] ?? []));
        $keys = array_map('strval', $keys);
        sort($keys);

        return collect($keys)->map(function (string $key) use ($source, $values, $overrides, $locale, $locked): array {
            $value = $values[$key] ?? null;
            $status = match (true) {
                $value === null || trim($value) === '' => TranslationKeyStatus::Missing,
                isset($overrides[$locale][$key]) => TranslationKeyStatus::Overridden,
                default => TranslationKeyStatus::Translated,
            };

            return [
                'key' => $key,
                'source' => $source[$key] ?? null,
                'value' => $value,
                'status' => $status,
                'locked' => isset($locked[$key]),
            ];
        })->values();
    }

    /**
     * Tab counts: all keys, missing in $locale, missing in Arabic, locked.
     *
     * @return array{all: int, missing: int, missing_ar: int, locked: int}
     */
    public function counts(TranslationFile $file, string $locale): array
    {
        $rows = $this->rows($file, $locale);
        $missingAr = $locale === 'ar'
            ? $rows->where('status', TranslationKeyStatus::Missing)->count()
            : $this->rows($file, 'ar')->where('status', TranslationKeyStatus::Missing)->count();

        return [
            'all' => $rows->count(),
            'missing' => $rows->where('status', TranslationKeyStatus::Missing)->count(),
            'missing_ar' => $missingAr,
            'locked' => $rows->where('locked', true)->count(),
        ];
    }

    /**
     * One key with every editor language value (edit / view modal).
     *
     * @return array{key: string, values: array<string, string|null>, disk: array<string, string|null>, locked: bool}
     */
    public function entry(TranslationFile $file, string $key): array
    {
        $overrides = $this->overrides->mapForFile($file->id);
        $values = [];
        $disk = [];

        foreach (array_keys($this->locales()) as $locale) {
            $disk[$locale] = $this->registry->fileLines($file, $locale)[$key] ?? null;
            $values[$locale] = $overrides[$locale][$key] ?? $disk[$locale];
        }

        if (array_filter($values, fn (?string $value): bool => $value !== null) === []) {
            throw TranslationRuleException::unknownKey($key);
        }

        return ['key' => $key, 'values' => $values, 'disk' => $disk, 'locked' => $this->locks->isLocked($file->id, $key)];
    }

    public function save(TranslationValuesData $data, ?int $adminId = null): void
    {
        $file = $this->findFile($data->file);

        if ($this->locks->isLocked($file->id, $data->key)) {
            throw TranslationRuleException::keyLocked($data->key);
        }

        $this->write($file, $data->key, $data->values, $adminId);
    }

    public function addKey(TranslationValuesData $data, ?int $adminId = null): void
    {
        $file = $this->findFile($data->file);

        if ($this->rows($file, 'en')->contains('key', $data->key)) {
            throw TranslationRuleException::keyExists($data->key);
        }

        $this->write($file, $data->key, $data->values, $adminId);
    }

    public function createFile(TranslationFileData $data, ?int $adminId = null): TranslationFile
    {
        $file = TranslationFile::fromId($data->fileId()) ?? throw TranslationRuleException::unknownFile();

        if ($file->isStorefront() || $file->id === TranslationFile::JSON || $this->registry->find($file->id) !== null) {
            throw TranslationRuleException::fileExists($file->id);
        }

        if ($file->namespace !== null && ! array_key_exists($file->namespace, $this->registry->moduleNamespaces())) {
            throw TranslationRuleException::unknownFile();
        }

        $this->write($file, $data->key, $data->values, $adminId);
        $this->registry->flush();

        return $file;
    }

    /**
     * Drop the admin's values of a key (back to the files; keys created in the editor disappear).
     */
    public function revert(string $fileId, string $key): void
    {
        $file = $this->findFile($fileId);

        if ($this->locks->isLocked($file->id, $key)) {
            throw TranslationRuleException::keyLocked($key);
        }

        $this->overrides->forget($file->id, $key);
        $this->flushed($file);
    }

    /**
     * key => value of a file in a locale (files + overrides) for the JSON export.
     *
     * @return array<string, string>
     */
    public function export(TranslationFile $file, string $locale): array
    {
        return $this->rows($file, $locale)
            ->filter(fn (array $row): bool => $row['value'] !== null && $row['value'] !== '')
            ->mapWithKeys(fn (array $row): array => [$row['key'] => (string) $row['value']])
            ->all();
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function write(TranslationFile $file, string $key, array $values, ?int $adminId): void
    {
        $locales = $this->locales();

        $this->transaction(function () use ($file, $key, $values, $adminId, $locales): void {
            foreach ($values as $locale => $value) {
                if (! array_key_exists((string) $locale, $locales)) {
                    continue;
                }

                $disk = $this->registry->fileLines($file, (string) $locale)[$key] ?? null;
                $value = $value === null ? '' : (string) $value;

                if ($value === '' || $value === $disk) {
                    $this->overrides->forget($file->id, $key, (string) $locale);

                    continue;
                }

                $this->overrides->put($file->id, $key, (string) $locale, $value, $adminId);
            }
        }, $this->overrides->query()->getModel()->getConnectionName());

        $this->flushed($file);
    }

    private function flushed(TranslationFile $file): void
    {
        $loader = $this->translator->getLoader();

        if ($loader instanceof OverridingTranslationLoader) {
            $loader->flush();
        }

        $this->translator->setLoaded([]);

        if ($file->isStorefront()) {
            $this->catalog->flush();
            $this->dispatcher->dispatchAll();
        }
    }
}
