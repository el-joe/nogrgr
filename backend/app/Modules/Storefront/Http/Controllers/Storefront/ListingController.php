<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Http\Controllers\Storefront\Concerns\RendersListings;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductListing;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Best selling / New in / Top rated / Offers (Figma 1:1191, variant notes 137:113051).
 */
class ListingController extends Controller
{
    use RendersListings;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontSeo $seo,
    ) {}

    public function bestSelling(Request $request): View|JsonResponse
    {
        return $this->show($request, ListingKind::BestSelling);
    }

    public function newIn(Request $request): View|JsonResponse
    {
        return $this->show($request, ListingKind::NewIn);
    }

    public function topRated(Request $request): View|JsonResponse
    {
        return $this->show($request, ListingKind::TopRated);
    }

    public function offers(Request $request): View|JsonResponse
    {
        return $this->show($request, ListingKind::Offers);
    }

    private function show(Request $request, ListingKind $kind): View|JsonResponse
    {
        $query = ListingQuery::fromRequest($request, $kind);
        $result = $this->listing->listing($query);
        $title = __st('catalog.listing.'.$kind->textKey().'.title');
        $crumbs = [['name' => __st('shell.home'), 'url' => route('storefront.home')], ['name' => $title, 'url' => route($kind->routeName())]];

        return $this->listingResponse($request, 'pages.listing', $query, $result, [
            'kind' => $kind,
            'title' => $title,
            'crumbs' => $crumbs,
            'category' => null,
            'categoryOptions' => array_map(static fn (array $node): array => ['id' => $node['category']->id, 'name' => (string) $node['category']->name], $this->categories->tree()),
            'cardTag' => match ($kind) {
                ListingKind::BestSelling => 'best',
                ListingKind::NewIn => 'new',
                ListingKind::TopRated => 'rating',
                ListingKind::Offers => 'discount',
                default => null,
            },
            'seo' => $this->seo->listing($title, __st('catalog.listing.'.$kind->textKey().'.description'), $result->cards, $crumbs, ['page' => $query->page], $query->activeFilters() === 0),
        ]);
    }
}
