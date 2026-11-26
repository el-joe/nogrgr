<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Services\Catalog\ProductPageService;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Product details (1:3380 / m 1:3073). 404 when the store does not sell the product in
 * the shopping country (or it is inactive); a wrong / missing slug redirects (301) to the
 * canonical URL.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductPageService $pages,
        private readonly StorefrontSeo $seo,
    ) {}

    public function __invoke(Request $request, int $product, ?string $slug = null): View|RedirectResponse
    {
        $page = $this->pages->find($product, $request->integer('variant') ?: null);
        abort_if($page === null, 404);

        if ($slug !== $page->slug) {
            return redirect()->to($page->url.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }

        return StorefrontView::make('pages.product', [
            'page' => $page,
            'seo' => $this->seo->product($page),
        ]);
    }
}
