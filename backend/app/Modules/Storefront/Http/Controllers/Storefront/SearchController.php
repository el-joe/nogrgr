<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Http\Controllers\Storefront\Concerns\RendersListings;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductListing;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search results (reuse 1:1191, notes 137:113051) and the header autocomplete (1:1163).
 */
class SearchController extends Controller
{
    use RendersListings;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontSeo $seo,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = ListingQuery::fromRequest($request, ListingKind::Search);
        $result = $this->listing->listing($query);
        $title = $query->term !== null ? __st('search.results_for', ['term' => $query->term, 'count' => $result->total]) : __st('search.title');
        $crumbs = [['name' => __st('shell.home'), 'url' => route('storefront.home')], ['name' => __st('search.title'), 'url' => route('storefront.search')]];

        return $this->listingResponse($request, 'pages.listing', $query, $result, [
            'kind' => ListingKind::Search,
            'title' => $title,
            'crumbs' => $crumbs,
            'category' => null,
            'categoryOptions' => array_map(static fn (array $node): array => ['id' => $node['category']->id, 'name' => (string) $node['category']->name], $this->categories->tree()),
            'cardTag' => null,
            'popular' => $result->isEmpty() ? $this->cards->make($this->listing->rail(ListingKind::BestSelling, 5) ?: $this->listing->rail(ListingKind::NewIn, 5)) : [],
            'seo' => $this->seo->listing($title, null, $result->cards, $crumbs, ['q' => $query->term ?? '', 'page' => $query->page], false),
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $term = ListingQuery::term($request->query('q'));

        if ($term === null || mb_strlen($term) < 2) {
            return new JsonResponse(['data' => ['term' => $term, 'products' => [], 'categories' => []]]);
        }

        $needle = mb_strtolower($term);
        $categories = $this->categories->visible()
            ->filter(static fn ($category): bool => str_contains(mb_strtolower((string) $category->name), $needle))
            ->take(4)
            ->map(static fn ($category): array => ['name' => (string) $category->name, 'url' => route('storefront.category', ['slug' => $category->slug])])
            ->values()
            ->all();

        $products = array_map(fn (IndexEntry $entry): array => [
            'id' => $entry->id,
            'name' => $entry->name,
            'url' => ProductCards::url($entry->id, $entry->slug),
            'image' => $entry->image,
            'price' => $this->cards->card($entry)->price,
        ], $this->listing->suggest($term));

        return new JsonResponse([
            'data' => [
                'term' => $term,
                'products' => $products,
                'categories' => $categories,
                'search_url' => route('storefront.search', ['q' => $term]),
            ],
        ]);
    }
}
