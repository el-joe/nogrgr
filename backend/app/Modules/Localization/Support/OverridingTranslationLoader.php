<?php

namespace App\Modules\Localization\Support;

use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use Closure;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Translator loader that merges the admin's central edits (translation_overrides,
 * ADM-092) over the `lang/*` files, so edits survive deploys. Wraps the framework
 * FileLoader; overrides are read once per locale (one central query) and flushed by
 * the editor after a save. Storefront static texts do not go through here (they are
 * resolved by StaticTextResolver).
 *
 * @mixin \Illuminate\Translation\FileLoader
 */
class OverridingTranslationLoader implements Loader
{
    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private array $overrides = [];

    /**
     * @param  Closure(): TranslationOverrideRepositoryContract  $repository
     */
    public function __construct(
        private readonly Loader $files,
        private readonly Closure $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->files->load($locale, $group, $namespace);
        $overrides = $this->overridesFor((string) $locale)[TranslationFile::idFor((string) $group, $namespace)] ?? [];

        if ($overrides === []) {
            return $lines;
        }

        foreach ($overrides as $key => $value) {
            if ($group === '*') {
                $lines[$key] = $value;
            } else {
                Arr::set($lines, $key, $value);
            }
        }

        return $lines;
    }

    /**
     * Lines of the files only (no overrides), for the editor.
     *
     * @return array<string, mixed>
     */
    public function loadFromFiles(string $locale, string $group, ?string $namespace = null): array
    {
        return $this->files->load($locale, $group, $namespace);
    }

    public function flush(): void
    {
        $this->overrides = [];
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->files->namespaces();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->files->{$method}(...$arguments);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function overridesFor(string $locale): array
    {
        if (array_key_exists($locale, $this->overrides)) {
            return $this->overrides[$locale];
        }

        try {
            return $this->overrides[$locale] = ($this->repository)()->mapForLocale($locale);
        } catch (Throwable) {
            // Central table not migrated yet (fresh install, migrations): files only.
            return $this->overrides[$locale] = [];
        }
    }
}
