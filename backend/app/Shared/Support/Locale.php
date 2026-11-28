<?php

namespace App\Shared\Support;

final class Locale
{
    /**
     * @var list<string>
     */
    public const RTL = ['ar', 'fa', 'he', 'ur'];

    public static function isRtl(?string $locale = null): bool
    {
        $language = strtolower(explode('_', str_replace('-', '_', $locale ?? app()->getLocale()))[0]);

        return in_array($language, self::RTL, true);
    }

    public static function direction(?string $locale = null): string
    {
        return self::isRtl($locale) ? 'rtl' : 'ltr';
    }

    /**
     * Enabled interface languages: code => [name, native, dir].
     *
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    public static function enabled(): array
    {
        /** @var array<string, array{name: string, native: string, dir: string}> $locales */
        $locales = (array) config('app.supported_locales', []);

        return $locales !== [] ? $locales : ['en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr']];
    }

    public static function isSupported(?string $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, self::enabled());
    }

    /**
     * ICU locale for numbers: Latin digits in every language (Figma RTL frame 56:17016
     * keeps 1,248 / 30 / 2026 in Latin digits), with the locale's separators and words.
     */
    public static function numberLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return str_contains($locale, '@') ? $locale : $locale.'@numbers=latn';
    }

    public static function htmlLang(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }

    /**
     * Visitor country (ISO-3166 alpha-2) for intl-tel-input pre-selection.
     * Trusts the CDN header when present, falls back to config('app.default_country').
     */
    public static function visitorCountry(): string
    {
        $country = request()->header('CF-IPCountry') ?? request()->header('X-Country-Code');

        if (is_string($country) && preg_match('/^[A-Za-z]{2}$/', $country) === 1 && strtoupper($country) !== 'XX') {
            return strtolower($country);
        }

        // Old storefront fallback: the region of Accept-Language ("en-EG" → EG).
        if (preg_match('/[a-z]{2,3}-([A-Z]{2})/', (string) request()->header('Accept-Language'), $match) === 1) {
            return strtolower($match[1]);
        }

        return strtolower((string) config('app.default_country', 'sa'));
    }
}
