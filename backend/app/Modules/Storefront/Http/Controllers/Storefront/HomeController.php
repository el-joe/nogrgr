<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Services\Catalog\HomeService;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store home (Ecommet 1:1002 / m 1:2656) and its "explore your interests" tab fragment.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly HomeService $home,
        private readonly StorefrontSeo $seo,
    ) {}

    public function __invoke(): View
    {
        $theme = app(StorefrontContext::class)->theme();
        $extra = $theme->family === ThemeFamily::Elora ? $this->home->elora() + ['variant' => $theme->variant] : [];

        return StorefrontView::make('pages.home', $this->home->sections() + $extra + ['seo' => $this->seo->home()]);
    }

    /**
     * Cards of one home tab page: JSON {html, next_url, total}.
     */
    public function tab(Request $request, string $tab): JsonResponse
    {
        $page = max(1, min(100, $request->integer('page', 1)));
        $result = $this->home->tab($tab, $page);
        abort_if($result === null, 404);

        return new JsonResponse([
            'html' => view(StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'variant' => 'grid', 'tag' => null, 'ribbon' => false, 'offset' => ($page - 1) * $result->perPage])->render(),
            'next_url' => $result->hasMore() ? route('storefront.home.tab', ['tab' => $tab, 'page' => $page + 1]) : null,
            'total' => $result->total,
            'page' => $page,
        ]);
    }
}
