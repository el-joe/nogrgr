<?php

namespace App\Modules\Storefront\Support\CustomThemes;

use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeExtension;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * `url(name, params)` of custom themes: only the storefront pages in ThemeExtension::ROUTES.
 * Route parameters: product {id, slug}, category {slug}, page {slug}; other params become
 * the query string (search {q}, listings {page, sort}).
 */
final class ThemeUrls
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function make(string $name, array $params): ?string
    {
        $route = ThemeExtension::ROUTES[$name] ?? null;

        if ($route === null || ! Route::has($route)) {
            return null;
        }

        $params = array_filter($params, static fn (mixed $value): bool => is_scalar($value) && $value !== '');

        if ($name === 'product') {
            $params['product'] = $params['id'] ?? null;
            unset($params['id']);
        }

        try {
            return route($route, $params);
        } catch (Throwable) {
            return null;
        }
    }
}
