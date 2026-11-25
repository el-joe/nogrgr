<?php

use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;

if (! function_exists('__st')) {
    /**
     * Storefront static text of the current store (decision #45), e.g.
     * __st('shell.footer.copyright', ['year' => 2026, 'name' => $store]).
     * Keys with "::" are panel/module translations and go to the translator.
     *
     * @param  array<string, mixed>  $replace
     */
    function __st(string $key, array $replace = [], ?string $locale = null): string
    {
        return app(StaticTextResolver::class)->get($key, $replace, $locale);
    }
}
