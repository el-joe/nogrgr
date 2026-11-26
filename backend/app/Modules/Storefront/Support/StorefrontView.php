<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Contracts\View\View;

/**
 * Theme-family view resolution: `storefront::themes.{family}.{name}`, falling back to
 * the Ecommet family while a family's inner pages are not built yet. Home pages differ
 * per variant (`pages.home` resolves `pages.home-v{n}` first).
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

        if ($name === 'pages.home') {
            $candidates[] = 'storefront::themes.'.$theme->family->value.'.pages.home-v'.$theme->variant;
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
