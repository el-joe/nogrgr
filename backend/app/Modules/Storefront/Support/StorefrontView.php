<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Storefront\Services\CustomThemes\ThemeViewModels;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\CustomThemes\CustomThemeView;
use Illuminate\Contracts\View\View;

/**
 * Theme-family view resolution: `storefront::themes.{family}.{name}`, falling back to
 * the Ecommet family while a family's inner pages are not built yet. Home pages differ
 * per variant (`pages.home`, `pages.listing` and `pages.product` resolve `…-v{n}` first).
 *
 *   return StorefrontView::make('pages.product', ['product' => $page]);
 */
final class StorefrontView
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $name, array $data = []): View
    {
        $theme = app(StorefrontContext::class)->theme();

        // A store with a live custom Twig theme (or a previewed version) renders its themed
        // pages through it; secure pages stay on the built-in theme (decision #121).
        if ($theme->customVersionId !== null && app(ThemeViewModels::class)->templateFor($name, $data) !== null) {
            return new CustomThemeView($theme->customVersionId, $name, $data);
        }

        return view(self::resolve($name), $data);
    }

    /**
     * A shared storefront component view (`components.storefront.{name}`) or the family's own
     * version (`components.storefront.{family}.inner.{name}`) when the family has one. Used for
     * the server-rendered fragments (cart body / summary, mini cart, checkout summary).
     */
    public static function component(string $name): string
    {
        $family = app(StorefrontContext::class)->theme()->family->value;
        $themed = 'components.storefront.'.$family.'.inner.'.$name;

        return view()->exists($themed) ? $themed : 'components.storefront.'.$name;
    }

    public static function resolve(string $name): string
    {
        $theme = app(StorefrontContext::class)->theme();
        $candidates = [];

        /* Pages a variant may draw its own way: `pages.{name}-v{n}` wins over the family page. */
        if ($name === 'pages.home' || $name === 'pages.listing' || $name === 'pages.product') {
            $candidates[] = 'storefront::themes.'.$theme->family->value.'.'.$name.'-v'.$theme->variant;
        }

        $candidates[] = 'storefront::themes.'.$theme->family->value.'.'.$name;
        $candidates[] = 'storefront::themes.'.StorefrontTheme::DEFAULT_FAMILY.'.'.$name;

        foreach ($candidates as $candidate) {
            if (view()->exists($candidate)) {
                return $candidate;
            }
        }

        return end($candidates);
    }
}
