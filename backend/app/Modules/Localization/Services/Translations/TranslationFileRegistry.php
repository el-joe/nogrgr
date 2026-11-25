<?php

namespace App\Modules\Localization\Services\Translations;

use App\Modules\Localization\Enums\TranslationFileKind;
use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Support\TranslationFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Translation\Translator;

/**
 * Discovers the translation files the admin editor (ADM-092) works on: the storefront
 * static text base set, lang/{locale}.json, lang/{locale}/*.php and every module's
 * resources/lang/{locale}/*.php, plus files created in the editor (overrides only).
 * Reads their lines from disk, flattened to dotted keys.
 */
class TranslationFileRegistry
{
    /**
     * @var array<string, TranslationFile>|null
     */
    private ?array $files = null;

    public function __construct(
        private readonly StaticTextCatalog $catalog,
        private readonly TranslationOverrideRepositoryContract $overrides,
        private readonly Translator $translator,
    ) {}

    /**
     * @return array<string, TranslationFile> id => file, storefront first
     */
    public function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $files = [
            StaticTextCatalog::FILE => TranslationFile::fromId(StaticTextCatalog::FILE),
            TranslationFile::JSON => TranslationFile::fromId(TranslationFile::JSON),
        ];
        $php = [];

        foreach ($this->locales() as $locale) {
            foreach (glob(lang_path($locale.DIRECTORY_SEPARATOR.'*.php')) ?: [] as $path) {
                $php[] = basename($path, '.php');
            }

            foreach ($this->moduleNamespaces() as $namespace => $directory) {
                foreach (glob($directory.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.'*.php') ?: [] as $path) {
                    $php[] = $namespace.'::'.basename($path, '.php');
                }
            }
        }

        foreach (array_merge($php, $this->overrides->files()) as $id) {
            $file = TranslationFile::fromId($id);

            if ($file !== null && ! isset($files[$id])) {
                $files[$id] = $file;
            }
        }

        $sorted = array_filter($files, fn (?TranslationFile $file): bool => $file !== null && $file->kind === TranslationFileKind::Php);
        ksort($sorted);

        /** @var array<string, TranslationFile> $result */
        $result = array_filter([
            StaticTextCatalog::FILE => $files[StaticTextCatalog::FILE],
            TranslationFile::JSON => $files[TranslationFile::JSON],
        ]) + $sorted;

        return $this->files = $result;
    }

    public function find(string $id): ?TranslationFile
    {
        return $this->files()[$id] ?? null;
    }

    /**
     * id => label, for the file select.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return array_map(fn (TranslationFile $file): string => $file->label(), $this->files());
    }

    /**
     * Module translation namespaces (kebab module key => lang directory).
     *
     * @return array<string, string>
     */
    public function moduleNamespaces(): array
    {
        $modules = app_path('Modules');

        /** @var array<string, string> $namespaces */
        $namespaces = array_filter(
            $this->translator->getLoader()->namespaces(),
            fn (string $path): bool => str_starts_with($path, $modules) && is_dir($path),
        );
        ksort($namespaces);

        return $namespaces;
    }

    /**
     * key => value of the file on disk (no admin overrides).
     *
     * @return array<string, string>
     */
    public function fileLines(TranslationFile $file, string $locale): array
    {
        if (preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/', $locale) !== 1) {
            return [];
        }

        $lines = match ($file->kind) {
            TranslationFileKind::Storefront => $this->catalog->fileLines($locale),
            TranslationFileKind::Json => $this->jsonLines($locale),
            TranslationFileKind::Php => $this->phpLines($file, $locale),
        };

        ksort($lines);

        return $lines;
    }

    /**
     * Locales that have panel translation files on disk.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [];

        foreach (glob(lang_path('*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $code = basename($directory);

            if ($code !== 'vendor') {
                $locales[] = $code;
            }
        }

        return $locales;
    }

    public function flush(): void
    {
        $this->files = null;
    }

    /**
     * @return array<string, string>
     */
    private function jsonLines(string $locale): array
    {
        $path = lang_path($locale.'.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_filter(array_map(fn (mixed $value): ?string => is_string($value) ? $value : null, $decoded), 'is_string') : [];
    }

    /**
     * @return array<string, string>
     */
    private function phpLines(TranslationFile $file, string $locale): array
    {
        $directory = $file->namespace === null ? lang_path() : ($this->moduleNamespaces()[$file->namespace] ?? null);

        if ($directory === null || $file->group === null) {
            return [];
        }

        $path = $directory.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$file->group.'.php';

        if (! is_file($path) || ! Str::startsWith((string) realpath($path), (string) realpath($directory))) {
            return [];
        }

        $data = require $path;
        $lines = [];

        foreach (is_array($data) ? Arr::dot($data) : [] as $key => $value) {
            if (is_string($value)) {
                $lines[(string) $key] = $value;
            }
        }

        return $lines;
    }
}
