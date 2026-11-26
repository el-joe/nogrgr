<?php

namespace App\Modules\Storefront\Http\Controllers\StoreApi;

use App\Modules\Catalog\Models\Category;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Http\Controllers\StoreApi\Concerns\PreparesCatalogContext;
use App\Modules\Storefront\Http\Requests\StoreApi\AutocompleteRequest;
use App\Modules\Storefront\Http\Requests\StoreApi\SearchRequest;
use App\Modules\Storefront\Http\Resources\StoreApi\CatalogCards;
use App\Modules\Storefront\Http\Resources\StoreApi\ListingPresenter;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductListing;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Store API search (APP-009): results with the listing filters, and the type-ahead
 * suggestions (products + matching visible categories) — the theme search's services.
 * No results → `popular` (best sellers, else new in) like the theme's empty state.
 */
class SearchController extends Controller
{
    use PreparesCatalogContext;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
    ) {}

    public function index(SearchRequest $request): JsonResponse
    {
        $this->prepareCatalog($request);
        $query = $request->toQuery(ListingKind::Search);
        $result = $this->listing->listing($query);

        return JsonResponder::success('', ListingPresenter::present($result, $query, $request) + [
            'term' => $query->term,
            'popular' => $result->isEmpty() ? CatalogCards::present($this->cards->make($this->listing->rail(ListingKind::BestSelling, 5) ?: $this->listing->rail(ListingKind::NewIn, 5)), $request) : [],
        ]);
    }

    public function autocomplete(AutocompleteRequest $request): JsonResponse
    {
        $this->prepareCatalog($request);
        $term = ListingQuery::term($request->validated('q'));

        if ($term === null || mb_strlen($term) < 2) {
            return JsonResponder::success('', ['term' => $term, 'products' => [], 'categories' => []]);
        }

        $needle = mb_strtolower($term);
        $categories = $this->categories->visible()
            ->filter(static fn (Category $category): bool => str_contains(mb_strtolower((string) $category->name), $needle))
            ->take(4)
            ->map(static fn (Category $category): array => ['id' => $category->id, 'name' => (string) $category->name, 'slug' => $category->slug])
            ->values()
            ->all();
        $entries = $this->listing->suggest($term, (int) ($request->validated('limit') ?? 6));

        return JsonResponder::success('', [
            'term' => $term,
            'products' => CatalogCards::present(array_map(fn (IndexEntry $entry) => $this->cards->card($entry), $entries), $request),
            'categories' => $categories,
        ]);
    }
}
