<?php

namespace App\Modules\Localization\Services\StaticTexts;

use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use Illuminate\Support\Arr;
use Throwable;

/**
 * The platform base set of storefront static texts (decision #45): ar + en source strings
 * kept as versioned data files (`Storefront/Database/Data/static-texts/{locale}/<group>.php`,
 * keys "<group>.<path>") plus the admin's central edits (translation_overrides, file
 * "storefront", ADM-092). Provisioning copies it into every store and the sync job keeps
 * stores in step after deploys. Memoised per process; flush() after an admin edit.
 */
class StaticTextCatalog
{
    public const FILE = 'storefront';

    /**
     * Locales of the base set (free for every store); en is the fallback.
     */
    public const BASE_LOCALES = ['en', 'ar'];

    public const FALLBACK = 'en';

    /**
     * @var array<string, array<string, string>>
     */
    private array $files = [];

    /**
     * @var array<string, array<string, string>>|null
     */
    private ?array $overrides = null;

    public function __construct(
        private readonly TranslationOverrideRepositoryContract $overrideRepository,
        private readonly ?string $path = null,
    ) {}

    public function path(): string
    {
        return $this->path ?? app_path('Modules/Storefront/Database/Data/static-texts');
    }

    /**
     * key => value from the data files only (what a deploy ships).
     *
     * @return array<string, string>
     */
    public function fileLines(string $locale): array
    {
        if (isset($this->files[$locale])) {
            return $this->files[$locale];
        }

        $lines = [];
        $directory = $this->path().DIRECTORY_SEPARATOR.$locale;

        if (preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/', $locale) === 1 && is_dir($directory)) {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
                $group = basename($file, '.php');
                $data = require $file;

                if (is_array($data)) {
                    foreach (Arr::dot($data) as $key => $value) {
                        if (is_string($value)) {
                            $lines[$group.'.'.$key] = $value;
                        }
                    }
                }
            }
        }

        ksort($lines);

        return $this->files[$locale] = $lines;
    }

    /**
     * key => value: data files merged with the admin's edits.
     *
     * @return array<string, string>
     */
    public function lines(string $locale): array
    {
        return array_replace($this->fileLines($locale), $this->overrides()[$locale] ?? []);
    }

    /**
     * Every base key (data files of the base locales + keys added by the admin).
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];

        foreach (self::BASE_LOCALES as $locale) {
            $keys += array_fill_keys(array_keys($this->lines($locale)), true);
        }

        $keys = array_map('strval', array_keys($keys));
        sort($keys);

        return $keys;
    }

    public function has(string $key): bool
    {
        foreach (self::BASE_LOCALES as $locale) {
            if (array_key_exists($key, $this->lines($locale))) {
                return true;
            }
        }

        return false;
    }

    public function value(string $key, string $locale): ?string
    {
        return $this->lines($locale)[$key] ?? null;
    }

    /**
     * Hash of the base value a store row is made from (ar/en: that locale; any other
     * locale: the English source the translation was made from).
     */
    public function hash(string $key, string $locale): ?string
    {
        $source = in_array($locale, self::BASE_LOCALES, true) ? $this->value($key, $locale) : $this->value($key, self::FALLBACK);

        return $source === null ? null : sha1($source);
    }

    /**
     * Fingerprint of the whole base set (changes on every deploy / admin edit that changes a text).
     */
    public function version(): string
    {
        $all = [];

        foreach (self::BASE_LOCALES as $locale) {
            $all[$locale] = $this->lines($locale);
        }

        return sha1((string) json_encode($all));
    }

    public function flush(): void
    {
        $this->files = [];
        $this->overrides = null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        try {
            return $this->overrides = $this->overrideRepository->mapForFile(self::FILE);
        } catch (Throwable) {
            // Central table not migrated yet (fresh install): files only.
            return $this->overrides = [];
        }
    }
}
