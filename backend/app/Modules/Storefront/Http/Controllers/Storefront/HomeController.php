<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Services\Catalog\HomeService;
use App\Modules\Storefront\Services\Layouts\HomeSectionHydrator;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Services\Themes\HomeLayoutService;
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
        $extra = in_array($theme->family, [ThemeFamily::Elora, ThemeFamily::Souqify], true) ? $this->home->elora() + ['variant' => $theme->variant] : [];
        /* Ecommet keeps the old Ecommet home's counts: 10 cards per grid page / "See More",
           5 lightning deals per live flash sale, newest products under "Explore your interests". */
        $sections = $theme->family === ThemeFamily::Ecommet
            ? $this->home->sections(HomeService::ECOMMET_PAGE, HomeService::ECOMMET_DEALS_PER_SALE, ListingSort::Newest)
            : $this->home->sections();

        return StorefrontView::make('pages.home', $sections + $extra + [
            'seo' => $this->seo->home(),
            // Home sections: the store's published page-builder layout (VEN-032) or the theme's admin
            // order (ADM-087, decision #120), disabled / hidden-in-country ones removed, settings applied.
            'homeSections' => app(HomeSectionHydrator::class)->hydrate($theme->family, app(HomeLayoutService::class)->sections($theme)),
        ]);
    }

    /**
     * Cards of one home tab page: JSON {html, next_url, total}.
     */
    public function tab(Request $request, string $tab): JsonResponse
    {
        $page = max(1, min(100, $request->integer('page', 1)));
        $ecommet = app(StorefrontContext::class)->theme()->family === ThemeFamily::Ecommet;
        $result = $ecommet
            ? $this->home->tab($tab, $page, HomeService::ECOMMET_PAGE, ListingSort::Newest)
            : $this->home->tab($tab, $page);
        abort_if($result === null, 404);

        return new JsonResponse([
            'html' => view(StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'variant' => 'grid', 'tag' => null, 'ribbon' => false, 'offset' => ($page - 1) * $result->perPage])->render(),
            'next_url' => $result->hasMore() ? route('storefront.home.tab', ['tab' => $tab, 'page' => $page + 1]) : null,
            'total' => $result->total,
            'page' => $page,
        ]);
    }
}
