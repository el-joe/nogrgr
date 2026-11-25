<?php

namespace App\Modules\Localization\Services\StaticTexts;

use App\Modules\Localization\Repositories\Contracts\TenantStaticTextRepositoryContract;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;
use Stringable;
use Throwable;

/**
 * Storefront static text lookup (`__st()` / `@st`, decision #45):
 * store value in the locale → base value in the locale → store value in English →
 * base value in English → the key. A key with "::" is a panel/module translation and
 * goes to the translator. Admin-locked keys (ADM-093) always show the base value in
 * ar/en. Each store's texts are cached per tenant + locale (busted on every write)
 * and memoised for the request.
 */
class StaticTextResolver
{
    public const CACHE_PREFIX = 'static-texts';

    /**
     * @var array<string, array<string, string>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly TenantStaticTextRepositoryContract $texts,
        private readonly StaticTextCatalog $catalog,
        private readonly TranslationKeyLockService $locks,
    ) {}

    /**
     * @param  array<string, mixed>  $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        if (str_contains($key, '::')) {
            $line = __($key, $replace, $locale);

            return is_string($line) ? $line : $key;
        }

        $locale ??= app()->getLocale();
        $line = $this->line($key, $locale)
            ?? ($locale === StaticTextCatalog::FALLBACK ? null : $this->line($key, StaticTextCatalog::FALLBACK))
            ?? $key;

        return $this->replace($line, $replace);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->line($key, $locale ?? app()->getLocale()) !== null;
    }

    /**
     * Drop the cached texts of the current store (all locales, or one).
     */
    public function forget(?string $locale = null): void
    {
        $tenant = $this->tenantKey();
        $this->loaded = [];

        if ($tenant === null) {
            return;
        }

        $locales = $locale !== null ? [$locale] : array_unique(array_merge(StaticTextCatalog::BASE_LOCALES, $this->safeLocales()));

        foreach ($locales as $code) {
            $this->cache()->forget($this->cacheKey($tenant, $code));
        }
    }

    /**
     * Resolved per call: in a store request the tenancy cache bootstrapper tags it per tenant.
     */
    private function cache(): Cache
    {
        return app('cache')->store();
    }

    public function cacheKey(string $tenant, string $locale): string
    {
        return self::CACHE_PREFIX.':'.$tenant.':'.$locale;
    }

    private function line(string $key, string $locale): ?string
    {
        if ($this->locks->isLocked(StaticTextCatalog::FILE, $key) && in_array($locale, StaticTextCatalog::BASE_LOCALES, true)) {
            return $this->catalog->value($key, $locale);
        }

        return $this->storeTexts($locale)[$key] ?? $this->catalog->value($key, $locale);
    }

    /**
     * @return array<string, string>
     */
    private function storeTexts(string $locale): array
    {
        $tenant = $this->tenantKey();

        if ($tenant === null) {
            return [];
        }

        $memo = $tenant.':'.$locale;

        if (isset($this->loaded[$memo])) {
            return $this->loaded[$memo];
        }

        try {
            /** @var array<string, string> $values */
            $values = $this->cache()->rememberForever($this->cacheKey($tenant, $locale), fn (): array => $this->texts->valuesFor($locale));
        } catch (Throwable) {
            // Store database not migrated to the static texts table yet: base set only.
            $values = [];
        }

        return $this->loaded[$memo] = $values;
    }

    /**
     * @return list<string>
     */
    private function safeLocales(): array
    {
        try {
            return $this->texts->locales();
        } catch (Throwable) {
            return [];
        }
    }

    private function tenantKey(): ?string
    {
        return tenancy()->initialized ? (string) tenant()?->getTenantKey() : null;
    }

    /**
     * Laravel-style ":name" / ":Name" / ":NAME" placeholders.
     *
     * @param  array<string, mixed>  $replace
     */
    private function replace(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        $pairs = [];

        foreach ($replace as $name => $value) {
            $value = (string) (is_scalar($value) || $value instanceof Stringable ? $value : '');
            $pairs[':'.$name] = $value;
            $pairs[':'.Str::ucfirst((string) $name)] = Str::ucfirst($value);
            $pairs[':'.Str::upper((string) $name)] = Str::upper($value);
        }

        uksort($pairs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return strtr($line, $pairs);
    }
}
