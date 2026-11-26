<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront\Concerns;

use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
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

        if ($request->boolean('fragment') || $request->ajax()) {
            return new JsonResponse([
                'html' => view(StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'variant' => $data['cardVariant'] ?? 'grid', 'tag' => $request->boolean('ribbon') ? null : ($data['cardTag'] ?? null), 'ribbon' => $request->boolean('ribbon'), 'offset' => ($query->page - 1) * $query->perPage])->render(),
                'next_url' => $next,
                'total' => $result->total,
                'page' => $result->page,
            ]);
        }

        return StorefrontView::make($view, $data + ['query' => $query, 'result' => $result, 'nextUrl' => $next]);
    }
}
