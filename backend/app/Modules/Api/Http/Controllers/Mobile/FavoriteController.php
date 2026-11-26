<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Concerns\ResolvesMobileCustomer;
use App\Modules\Api\Http\Requests\Mobile\FavoriteRequest;
use App\Modules\Api\Http\Requests\Mobile\PageRequest;
use App\Modules\Api\Http\Resources\Mobile\ProductSummaryResource;
use App\Modules\Api\Services\MobileFavoritesService;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wishlist (APP-019): paginated favorite products, all favorited ids (heart state on
 * every card), add, remove.
 */
class FavoriteController extends Controller
{
    use ResolvesMobileCustomer;

    public function __construct(
        private readonly MobileFavoritesService $favorites,
        private readonly MoneyPresenter $money,
    ) {}

    public function index(PageRequest $request): JsonResponse
    {
        $this->displayCurrency($request);
        $page = $this->favorites->page($this->customer($request), $request->perPage(), $request->page());

        return JsonResponder::success('', [
            'items' => array_map(static fn (IndexEntry $entry): array => (new ProductSummaryResource($entry, true))->resolve($request), $page['items']),
            'ids' => $page['ids'],
            'meta' => $page['meta'],
        ]);
    }

    public function store(FavoriteRequest $request): JsonResponse
    {
        $result = $this->favorites->add($this->customer($request), (int) $request->validated('store_product_id'));

        return JsonResponder::created((string) __('api::mobile.favorite_added'), $result);
    }

    public function destroy(Request $request, int $storeProduct): JsonResponse
    {
        return JsonResponder::success((string) __('api::mobile.favorite_removed'), $this->favorites->remove($this->customer($request), $storeProduct));
    }

    private function displayCurrency(Request $request): void
    {
        $currency = $request->header('X-Currency');
        $this->money->useDisplayCurrency(is_string($currency) ? $currency : null);
    }
}
