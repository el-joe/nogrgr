<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Catalog\Models\Category;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Http\Controllers\Storefront\Concerns\RendersListings;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductListing;
use App\Modules\Storefront\Services\Seo\StorefrontSeo;
use App\Modules\Storefront\Support\StorefrontView;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Categories browser (m 1:2979) and category listing (1:1693, filters 1:1796).
 */
class CategoryController extends Controller
{
    use RendersListings;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontSeo $seo,
    ) {}

    public function index(): View
    {
        $title = __st('catalog.categories.title');

        return StorefrontView::make('pages.categories', [
            'tree' => $this->categories->tree(),
            'title' => $title,
            'seo' => $this->seo->listing($title, __st('catalog.categories.description'), [], [['name' => __st('shell.home'), 'url' => route('storefront.home')], ['name' => $title, 'url' => route('storefront.categories.index')]]),
        ]);
    }

    /**
     * /category without a slug: every product of the store (visitor's country) with the top-level
     * categories as the tile row, like the old Ecommet "all products" category page.
     */
    public function all(Request $request): View|JsonResponse
    {
        $query = ListingQuery::fromRequest($request, ListingKind::Category);
        $result = $this->listing->listing($query, null);
        $title = __st('catalog.categories.title');
        $crumbs = [['name' => __st('shell.home'), 'url' => route('storefront.home')], ['name' => $title, 'url' => route('storefront.category.all')]];

        return $this->listingResponse($request, 'pages.listing', $query, $result, [
            'kind' => ListingKind::Category,
            'title' => $title,
            'crumbs' => $crumbs,
            'category' => null,
            'parentCategory' => null,
            'subcategories' => array_map(static fn (array $node): Category => $node['category'], $this->categories->tree()),
            'categoryOptions' => [],
            'cardTag' => null,
            'seo' => $this->seo->listing(
                $title,
                __st('catalog.categories.description'),
                $result->cards,
                $crumbs,
                ['page' => $query->page],
                $query->activeFilters() === 0,
            ),
        ]);
    }

    public function show(Request $request, string $slug): View|JsonResponse
    {
        $category = $this->categories->findBySlug($slug);
        abort_if($category === null, 404);

        $query = ListingQuery::fromRequest($request, ListingKind::Category);
        $result = $this->listing->listing($query, $category);
        $crumbs = [['name' => __st('shell.home'), 'url' => route('storefront.home')]];

        foreach ($this->categories->ancestry($category) as $node) {
            $crumbs[] = ['name' => (string) $node->name, 'url' => route('storefront.category', ['slug' => $node->slug])];
        }

        $children = $this->categories->children($category);

        return $this->listingResponse($request, 'pages.listing', $query, $result, [
            'kind' => ListingKind::Category,
            'title' => (string) $category->name,
            'crumbs' => $crumbs,
            'category' => $category,
            'parentCategory' => $parent = $category->parent_id !== null ? $this->categories->find($category->parent_id) : null,
            // Old Ecommet behaviour: a category's own children, else its siblings, else the top-level categories.
            'subcategories' => $children !== [] ? $children : ($parent !== null ? $this->categories->children($parent) : array_map(static fn (array $node): Category => $node['category'], $this->categories->tree())),
            'categoryOptions' => [],
            'cardTag' => null,
            'seo' => $this->seo->listing(
                (string) ($category->meta_title ?: $category->name),
                $category->meta_description ?: ($category->description ?: __st('catalog.listing.category.description', ['category' => $category->name])),
                $result->cards,
                $crumbs,
                ['page' => $query->page],
                $query->activeFilters() === 0,
            ),
        ]);
    }
}
