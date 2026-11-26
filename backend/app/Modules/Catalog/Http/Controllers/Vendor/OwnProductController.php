<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\DataTables\Vendor\OwnProductsDataTable;
use App\Modules\Catalog\Enums\OwnProductSection;
use App\Modules\Catalog\Http\Requests\Vendor\ListFilterRequest;
use App\Modules\Catalog\Http\Requests\Vendor\OwnProductRequest;
use App\Modules\Catalog\Http\Requests\Vendor\OwnStockRequest;
use App\Modules\Catalog\Http\Requests\Vendor\StoreToggleRequest;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\OwnProductService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyService;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Own products (VEN-016 overview sections, 016a section page, 016b inventory, 016c
 * create / edit).
 */
class OwnProductController extends Controller
{
    public function __construct(
        private readonly OwnProductService $own,
        private readonly StoreProductPricingService $pricing,
        private readonly StoreTaxonomyService $taxonomy,
    ) {}

    public function index(): View
    {
        return view('catalog::vendor.own.index', [
            'sections' => $this->own->sections(),
            'total' => $this->own->count(),
            'pricing' => $this->pricing,
        ]);
    }

    public function section(string $section): View
    {
        $current = OwnProductSection::tryFrom($section) ?? abort(404);

        return view('catalog::vendor.own.section', ['section' => $current]);
    }

    public function inventory(): View
    {
        return view('catalog::vendor.own.inventory');
    }

    public function data(ListFilterRequest $request, OwnProductsDataTable $table): JsonResponse
    {
        return $table->forInventory($request->query('view') === 'inventory')->toJson($request->filters());
    }

    public function create(StoreCatalogService $catalog, CountryService $countries): View
    {
        return view('catalog::vendor.own.form', $this->formData(new StoreProduct(['is_active' => true, 'manage_stock' => true, 'is_taxable' => true]), $catalog, $countries));
    }

    public function store(OwnProductRequest $request): JsonResponse
    {
        $product = $this->own->create($request->toData());

        return JsonResponder::success((string) __('catalog::vendor.own.created'), ['id' => $product->id], vendor_route('catalog.own-products.edit', ['product' => $product->id]));
    }

    public function edit(Request $request, int $product, StoreCatalogService $catalog, CountryService $countries): View
    {
        $own = $this->own->find($product)->load(['variants', 'media']);
        $this->authorizeForUser($request->user('vendor'), 'update', $own);

        return view('catalog::vendor.own.form', $this->formData($own, $catalog, $countries));
    }

    public function update(OwnProductRequest $request, int $product): JsonResponse
    {
        $own = $this->own->find($product);
        $this->authorizeForUser($request->user('vendor'), 'update', $own);
        $this->own->update($own, $request->toData());

        return JsonResponder::success((string) __('catalog::vendor.own.updated'), redirect: vendor_route('catalog.own-products.edit', ['product' => $own->id]));
    }

    public function toggle(StoreToggleRequest $request, int $product): JsonResponse
    {
        $own = $this->own->find($product);
        $this->authorizeForUser($request->user('vendor'), 'update', $own);
        $this->own->setActive($own, $request->value());

        return JsonResponder::success((string) __('catalog::vendor.products.active_saved'));
    }

    public function stock(OwnStockRequest $request, int $product): JsonResponse
    {
        $own = $this->own->find($product);
        $this->authorizeForUser($request->user('vendor'), 'update', $own);
        $saved = $this->own->updateStock($own, $request->filled('stock') ? (int) $request->validated('stock') : null, $request->variantStock());

        return JsonResponder::success((string) __('catalog::vendor.own.stock_saved'), ['stock' => $saved->stock]);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $own = $this->own->find($product);
        $this->authorizeForUser($request->user('vendor'), 'delete', $own);
        $this->own->delete($own);

        return JsonResponder::success((string) __('catalog::vendor.own.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(StoreProduct $product, StoreCatalogService $catalog, CountryService $countries): array
    {
        $tenant = tenant();
        $selling = $tenant instanceof Tenant ? array_map('intval', $tenant->selling_country_ids ?? []) : [];

        return [
            'product' => $product,
            'categoryOptions' => $catalog->categoryOptions(),
            'storeCategoryOptions' => $this->taxonomy->ownCategoryOptions(),
            'countries' => $selling === [] ? collect() : $countries->active($selling)->values(),
        ];
    }
}
