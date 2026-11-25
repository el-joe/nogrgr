<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Services\LanguageService;
use App\Shared\Support\Locale;

/**
 * Locales of the catalog translation tabs: the interface languages (ar + en, always
 * required) followed by every other active central language (optional, filled by hand
 * or by the AI translation run).
 */
final class CatalogLocales
{
    /**
     * @var list<string>
     */
    public const REQUIRED = Language::CORE;

    public function __construct(private readonly LanguageService $languages) {}

    /**
     * code => [name, native, dir], interface languages first.
     *
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    public function all(): array
    {
        $locales = Locale::enabled();

        foreach ($this->languages->active() as $language) {
            if (! isset($locales[$language->code])) {
                $locales[$language->code] = [
                    'name' => (string) $language->name,
                    'native' => $language->native_name,
                    'dir' => $language->direction->value,
                ];
            }
        }

        return $locales;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function isRequired(string $code): bool
    {
        return in_array($code, self::REQUIRED, true);
    }

    /**
     * Validation rules for one translatable field: required in ar + en, optional elsewhere.
     *
     * @param  list<mixed>  $rules  rules applied to every filled locale
     * @return array<string, list<mixed>>
     */
    public function rules(string $field, array $rules, bool $required = true): array
    {
        $result = [$field => [$required ? 'required' : 'nullable', 'array']];

        foreach ($this->codes() as $code) {
            $result[$field.'.'.$code] = [$required && $this->isRequired($code) ? 'required' : 'nullable', ...$rules];
        }

        return $result;
    }

    /**
     * Keeps only the known locales with a non-empty value.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, string>
     */
    public function clean(?array $values): array
    {
        $clean = [];

        foreach ($this->codes() as $code) {
            $value = $values[$code] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $clean[$code] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * Value for a translatable column: every catalog locale present (null when empty) so a
     * cleared translation is really removed; [] when nothing is filled.
     *
     * @param  array<string, string>  $values
     * @return array<string, string|null>
     */
    public function forStorage(array $values): array
    {
        return $values === [] ? [] : array_merge(array_fill_keys($this->codes(), null), $values);
    }
}
