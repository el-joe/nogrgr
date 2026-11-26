<?php

namespace App\Modules\Api\Services;

use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Shared\Support\Locale;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The store's switched-on languages for the app (Vendor › Settings › Languages), in the
 * switcher order, with their central name / native name / direction. Falls back to the
 * platform interface languages (ar / en) when the store has no language rows yet.
 * Memoised per request (scoped).
 */
class MobileLocales
{
    /**
     * @var list<array{code: string, name: string, native: string, dir: string, is_default: bool}>|null
     */
    private ?array $languages = null;

    public function __construct(
        private readonly TenantLanguageRepositoryContract $tenantLanguages,
        private readonly LanguageRepositoryContract $languagesCatalog,
    ) {}

    /**
     * @return list<array{code: string, name: string, native: string, dir: string, is_default: bool}>
     */
    public function all(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        try {
            /** @var Collection<int, TenantLanguage> $rows */
            $rows = $this->tenantLanguages->ordered()->filter(static fn (TenantLanguage $row): bool => $row->is_enabled)->values();
        } catch (Throwable) {
            $rows = collect();
        }

        $central = $rows->isEmpty() ? collect() : collect($this->languagesCatalog->all())->keyBy('code');
        $languages = [];

        foreach ($rows as $row) {
            /** @var Language|null $language */
            $language = $central->get($row->language_code);
            $platform = Locale::enabled()[$row->language_code] ?? null;
            $languages[] = [
                'code' => $row->language_code,
                'name' => $language->name ?? $platform['name'] ?? strtoupper($row->language_code),
                'native' => $language->native_name ?? $platform['native'] ?? strtoupper($row->language_code),
                'dir' => $language !== null ? $language->direction->value : ($platform['dir'] ?? Locale::direction($row->language_code)),
                'is_default' => $row->is_default,
            ];
        }

        if ($languages === []) {
            foreach (Locale::enabled() as $code => $info) {
                $languages[] = ['code' => (string) $code, 'name' => $info['name'], 'native' => $info['native'], 'dir' => $info['dir'], 'is_default' => $code === config('app.locale')];
            }
        }

        return $this->languages = $languages;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_column($this->all(), 'code');
    }

    public function default(): string
    {
        foreach ($this->all() as $language) {
            if ($language['is_default']) {
                return $language['code'];
            }
        }

        return $this->codes()[0] ?? (string) config('app.locale');
    }
}
