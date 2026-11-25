<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\DataTables\Vendor\OpportunitiesDataTable;
use App\Modules\Catalog\Http\Requests\Vendor\ListFilterRequest;
use App\Modules\Catalog\Http\Requests\Vendor\MarketSelectionRequest;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Today's chances (VEN-015) and the select markets modal (VEN-015a, already-added state VEN-015b).
 */
class OpportunityController extends Controller
{
    public function __construct(private readonly StoreCatalogService $catalog) {}

    public function index(): View
    {
        return view('catalog::vendor.opportunities.index', [
            'categoryOptions' => $this->catalog->categoryOptions(),
        ]);
    }

    public function data(ListFilterRequest $request, OpportunitiesDataTable $table): JsonResponse
    {
        return $table->toJson($request->filters());
    }

    public function markets(Request $request, int $central): View
    {
        try {
            $product = $this->catalog->offeredOrFail($central);
        } catch (DomainException) {
            abort(404);
        }

        $storeProduct = $this->catalog->storeProductFor($product->id);
        $markets = $this->catalog->markets($storeProduct);

        return view('catalog::vendor.opportunities._markets', [
            'product' => $product,
            'countries' => $this->catalog->marketCountries($product),
            'markets' => $markets,
            'editing' => $markets->isEmpty() || $request->boolean('edit'),
            'canManage' => (bool) $request->user('vendor')?->can('catalog.products.manage'),
        ]);
    }

    public function saveMarkets(MarketSelectionRequest $request, int $central): JsonResponse
    {
        try {
            $this->catalog->saveMarkets($central, $request->toData());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success((string) __('catalog::vendor.opportunities.saved'));
    }
}
