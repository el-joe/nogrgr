<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront\Concerns;

use App\Modules\Catalog\Models\Category;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\CategoryTiles;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\StorefrontView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listing pages answer two ways on the same URL: the full page, or — for "See more"
 * (`?fragment=1` / XHR) — the next page of cards as JSON {html, next_url, total}.
 * "See more" is a real link to ?page=n+1, so crawlers and no-JS visitors still page.
 */
trait RendersListings
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function listingResponse(Request $request, string $view, ListingQuery $query, ListingResult $result, array $data): View|JsonResponse
    {
        $next = $result->hasMore() ? $request->fullUrlWithQuery(['page' => $query->page + 1, 'fragment' => null]) : null;
        /* Only Elora v1 (base) has its own listing; variants 2–6 keep the family page and cards. */
        $theme = app(StorefrontContext::class)->theme();
        $eloraBase = $theme->family->value === 'elora' && $theme->variant === 1;
        $deliveryBy = $eloraBase ? $this->deliveryBy() : null;

        if ($request->boolean('fragment') || $request->ajax()) {
            return new JsonResponse([
                'html' => view($eloraBase ? 'storefront::themes.elora.partials.listing-cards' : StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'variant' => $data['cardVariant'] ?? 'grid', 'tag' => $request->boolean('ribbon') ? null : ($data['cardTag'] ?? null), 'ribbon' => $request->boolean('ribbon'), 'offset' => ($query->page - 1) * $query->perPage, 'deliveryBy' => $deliveryBy])->render(),
                'next_url' => $next,
                'total' => $result->total,
                'page' => $result->page,
            ]);
        }

        if ($eloraBase) {
            /* Category circles: the page's sub-categories, else the store's top-level ones. */
            $circles = ($data['kind'] ?? null) === ListingKind::Category
                ? ($data['subcategories'] ?? [])
                : array_map(static fn (array $node): Category => $node['category'], app(CategoryCatalog::class)->tree());
            $data += ['tiles' => app(CategoryTiles::class)->make(array_values($circles)), 'deliveryBy' => $deliveryBy];
        }

        return StorefrontView::make($view, $data + ['query' => $query, 'result' => $result, 'nextUrl' => $next]);
    }

    /**
     * "Delivered by 24 March" line of the Elora cards: latest estimate of the shopping country.
     */
    private function deliveryBy(): ?string
    {
        $countryId = app(StorefrontContext::class)->countryId();
        $date = $countryId !== null ? rescue(fn () => app(DeliveryEstimateService::class)->estimateFor($countryId)->maxDate, null, false) : null;

        return $date?->locale(app()->getLocale())->translatedFormat('j F');
    }
}
