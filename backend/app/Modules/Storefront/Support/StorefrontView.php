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
