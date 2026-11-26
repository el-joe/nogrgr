<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Services\Appearance\StorefrontAppearance;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * GET {store}/appearance/{family}.css — the store's appearance tokens (brand colour,
 * fonts, promo bar colours) as a same-origin stylesheet linked from the page head
 * (decision #91: no inline styles). URLs carry a content hash (?v=), so they cache long.
 */
class AppearanceStylesheetController extends Controller
{
    public function __invoke(string $family, StorefrontAppearance $appearance): Response
    {
        abort_if(ThemeFamily::tryFrom($family) === null, 404);

        return response($appearance->css($family))
            ->header('Content-Type', 'text/css; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=31536000, immutable')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
