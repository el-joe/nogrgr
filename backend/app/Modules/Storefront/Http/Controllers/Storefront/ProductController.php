<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Events\ProductViewed;
use App\Modules\Storefront\Services\Catalog\HomeService;
use App\Modules\Storefront\Services\Catalog\ProductPageService;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

    public function __invoke(Request $request, int $product, ?string $slug = null): View|RedirectResponse|JsonResponse
    {
        $relatedPage = max(1, min(20, $request->integer('related_page', 1)));

        if ($request->boolean('fragment') && $request->has('related_page')) {
            return $this->related($product, $relatedPage);
        }

        $page = $this->pages->find($product, $request->integer('variant') ?: null, $relatedPage);
        abort_if($page === null, 404);

        if ($slug !== $page->slug) {
            return redirect()->to($page->url.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }

        if (tenancy()->initialized && ! self::isPrefetch($request)) {
            ProductViewed::dispatch((int) tenant()->getTenantKey(), $product, $page->name);
        }

        return StorefrontView::make('pages.product', [
            'page' => $page,
            'categoryTabs' => app(HomeService::class)->tabs(),
            'seo' => $this->seo->product($page),
        ]);
    }

    /**
     * "You might also like" fragment: JSON {html, next_url}; not a product view.
     */
    private function related(int $product, int $page): JsonResponse
    {
        $result = $this->pages->relatedPage($product, $page);
        abort_if($result === null, 404);

        return new JsonResponse([
            'html' => view(StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result['cards'], 'offset' => ($page - 1) * $result['per_page']])->render(),
            'next_url' => $result['more'] ? route('storefront.product', ['product' => $product, 'related_page' => $page + 1]) : null,
        ]);
    }

    /**
     * Browser / link prefetches are not views (product performance, ADM-070j).
     */
    private static function isPrefetch(Request $request): bool
    {
        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));

        return str_contains($purpose, 'prefetch');
    }
}
