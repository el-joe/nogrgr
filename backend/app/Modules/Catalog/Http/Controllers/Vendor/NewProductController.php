<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\DataTables\Vendor\NewProductsDataTable;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Vendor\ImportProductsRequest;
use App\Modules\Catalog\Http\Requests\Vendor\ListFilterRequest;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * New central products (VEN-011 / 011a): offered products not in the store yet, preview,
 * import selected or all.
 */
class NewProductController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(private readonly StoreCatalogService $catalog) {}

    public function index(): View
    {
        return view('catalog::vendor.products.new', [
            'categoryOptions' => $this->catalog->categoryOptions(),
            'count' => $this->catalog->newProducts()->count(),
        ]);
    }

    public function data(ListFilterRequest $request, NewProductsDataTable $table): JsonResponse
    {
        return $table->toJson($request->filters());
    }

    public function show(int $central, StoreProductPricingService $pricing): View
    {
        try {
            $product = $this->catalog->offeredOrFail($central)->load(['media', 'variants']);
        } catch (DomainException) {
            abort(404);
        }

        $countries = $pricing->countriesFor($product);

        return view('catalog::vendor.products._preview', [
            'product' => $product,
            'countries' => $countries,
            'imported' => in_array($product->id, $this->catalog->importedCentralIds(), true),
            'defaultPercentage' => $pricing->defaultPercentage(),
            'unit' => $pricing->defaultUnit($product),
        ]);
    }

    public function import(ImportProductsRequest $request): JsonResponse
    {
        try {
            $count = $this->catalog->import($request->ids());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(trans_choice('catalog::vendor.new.imported', $count, ['count' => $count]), ['imported' => $count]);
    }
}
