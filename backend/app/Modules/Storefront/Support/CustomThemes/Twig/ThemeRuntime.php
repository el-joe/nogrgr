<?php

namespace App\Modules\Storefront\Support\CustomThemes\Twig;

use Closure;

/**
 * Everything the theme functions need for one render, set by the renderer (live store
 * request) or by the validator (fixture smoke render): the version's files and
 * strings, asset URL base, CSP nonce, settings, and the store-side callbacks (URLs,
 * money, head / scripts HTML). Missing assets / strings are collected as warnings.
 */
final class ThemeRuntime
{
    /**
     * @var list<string>
     */
    public array $warnings = [];

    /**
     * @param  array<string, true>  $assets  asset paths relative to assets/ ("css/theme.css")
     * @param  array<string, array<string, mixed>>  $strings  locale => theme strings
     * @param  array<string, mixed>  $settings  resolved vendor settings (id => value)
     * @param  Closure(string, array<string, mixed>): ?string  $url  named storefront URL, null when unknown
     * @param  Closure(int): string  $money  USD minor units → display string
     * @param  Closure(): string  $head  head HTML (SEO, CSP meta, context island, fonts)
     * @param  Closure(): string  $scripts  runtime script tag(s)
     * @param  Closure(string, array<string, mixed>): string  $staticText  platform static text (__st)
     */
    public function __construct(
        public readonly array $assets,
        public readonly string $assetBase,
        public readonly array $strings,
        public readonly string $locale,
        public readonly string $nonce,
        public readonly array $settings,
        public readonly string $csrfToken,
        public readonly Closure $url,
        public readonly Closure $money,
        public readonly Closure $head,
        public readonly Closure $scripts,
        public readonly Closure $staticText,
    ) {}

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true) && count($this->warnings) < 50) {
            $this->warnings[] = $message;
        }
    }
}
