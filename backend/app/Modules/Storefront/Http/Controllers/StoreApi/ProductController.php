<?php

namespace App\Modules\Storefront\Http\Controllers\StoreApi;

use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Events\ProductViewed;
use App\Modules\Storefront\Http\Controllers\StoreApi\Concerns\PreparesCatalogContext;
use App\Modules\Storefront\Http\Requests\StoreApi\ProductIndexRequest;
use App\Modules\Storefront\Http\Requests\StoreApi\ProductShowRequest;
use App\Modules\Storefront\Http\Resources\StoreApi\ListingPresenter;
use App\Modules\Storefront\Http\Resources\StoreApi\ProductDetailResource;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use App\Modules\Storefront\Services\Catalog\ProductListing;
use App\Modules\Storefront\Services\Catalog\ProductPageService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Store API products (APP-007 / APP-008): listings with the theme filters / sort / paging
 * (ProductListing over the storefront index, prices exactly as the storefront incl. flash
 * sales) and the product details (ProductPageService). 404 when the store does not sell
 * the product in the shopping country.
 */
class ProductController extends Controller
{
    use PreparesCatalogContext;

    public function __construct(
        private readonly ProductListing $listing,
        private readonly CategoryCatalog $categories,
        private readonly ProductPageService $pages,
        private readonly ProductIndex $index,
        private readonly ProductCards $cards,
    ) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        $this->prepareCatalog($request);
        $slug = $request->validated('category');
        $category = is_string($slug) ? $this->categories->findBySlug($slug) : null;

        if (is_string($slug) && $category === null) {
            return JsonResponder::error((string) __('api::store.category_not_found'), 404);
        }

        $query = $request->toQuery($category !== null ? ListingKind::Category : $request->kind());

        return JsonResponder::success('', ListingPresenter::present($this->listing->listing($query, $category), $query, $request));
    }

    public function show(ProductShowRequest $request, int $product): JsonResponse
    {
        $this->prepareCatalog($request);
        $variant = $request->validated('variant');
        $page = $this->pages->find($product, $variant !== null ? (int) $variant : null);

        if ($page === null) {
            return JsonResponder::error((string) __('api::store.product_not_found'), 404);
        }

        if (tenancy()->initialized) {
            ProductViewed::dispatch((int) tenant()->getTenantKey(), $product, $page->name);
        }

        $entry = $this->index->entry($product);
        $favorited = $entry !== null && $this->cards->card($entry)->favorited;

        return JsonResponder::success('', (new ProductDetailResource($page, $favorited))->toArray($request));
    }
}
