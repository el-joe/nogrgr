<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\DataTables\Vendor\StoreProductsDataTable;
use App\Modules\Catalog\DTOs\Vendor\StorePriceRow;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Vendor\ListFilterRequest;
use App\Modules\Catalog\Http\Requests\Vendor\StoreProductUpdateRequest;
use App\Modules\Catalog\Http\Requests\Vendor\StoreToggleRequest;
use App\Modules\Catalog\Services\Vendor\OwnProductService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Catalog\Services\Vendor\StoreProductExportService;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Modules\Shipping\Services\ProductShippingDetailsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Products (VEN-010 list, VEN-010e shipping details, VEN-012 product edit with
 * overrides). Imported central products only; own products have their own controller.
 */
class StoreProductController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly StoreCatalogService $catalog,
        private readonly StoreProductPricingService $pricing,
    ) {}

    public function index(OwnProductService $own): View
    {
        return view('catalog::vendor.products.index', [
            'categoryOptions' => $this->catalog->categoryOptions(),
            'newCount' => $this->catalog->newProducts()->count(),
            'ownCount' => $own->count(),
        ]);
    }

    public function data(ListFilterRequest $request, StoreProductsDataTable $table): JsonResponse
    {
        return $table->toJson($request->filters());
    }

    /**
     * Select2 options of imported products (edit request modal product picker).
     */
    public function options(ListFilterRequest $request, StoreCatalogReader $reader): JsonResponse
    {
        $page = $reader->withRelations($this->catalog->listing($request->filters()))->orderBy('sort_order')->orderByDesc('id')->paginate(20, ['*'], 'page', (int) $request->validated('page', 1));

        return response()->json([
            'results' => array_map(static fn (StoreProductView $view): array => [
                'id' => $view->id(),
                'text' => $view->name.($view->sku ? ' · '.$view->sku : ''),
            ], $reader->views($page->getCollection())),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    public function export(ListFilterRequest $request, StoreProductExportService $export): StreamedResponse
    {
        $filters = $request->filters();

        return response()->streamDownload(
            static fn () => $export->write(fopen('php://output', 'wb'), $filters),
            'store-products-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function edit(Request $request, int $product, StoreCatalogReader $reader): View
    {
        $storeProduct = $this->catalog->find($product);
        $this->authorizeForUser($request->user('vendor'), 'view', $storeProduct);
        $view = $reader->view($storeProduct);
        $rows = $this->pricing->table($storeProduct);

        return view('catalog::vendor.products.edit', [
            'product' => $storeProduct,
            'view' => $view,
            'rows' => $rows,
            'summary' => $this->pricing->summary($view, $rows),
            'categoryOptions' => $this->catalog->categoryOptions(),
            'badgeOptions' => $this->catalog->badgeOptions(),
            'canManage' => (bool) $request->user('vendor')?->can('catalog.products.manage'),
            'tab' => in_array($request->query('tab'), ['pricing', 'stock', 'categories', 'snapshot'], true) ? (string) $request->query('tab') : 'pricing',
        ]);
    }

    public function update(StoreProductUpdateRequest $request, int $product): JsonResponse
    {
        $storeProduct = $this->catalog->find($product);
        $this->authorizeForUser($request->user('vendor'), 'update', $storeProduct);

        return $this->attempt(
            fn () => $this->catalog->update($storeProduct, $request->toData()),
            (string) __('catalog::vendor.products.saved'),
            vendor_route('catalog.products.edit', ['product' => $storeProduct->id, 'tab' => $request->input('tab', 'pricing')]),
        );
    }

    /**
     * Live pricing tab preview: the submitted rows priced by Commerce\Pricing (nothing saved).
     */
    public function pricingPreview(StoreProductUpdateRequest $request, int $product, StoreCatalogReader $reader): JsonResponse
    {
        $storeProduct = $this->catalog->find($product);
        $table = $this->pricing->previewRows($storeProduct, $request->toData()->pricing);

        return JsonResponder::success(data: [
            'rows' => array_map(static fn (StorePriceRow $row): array => $row->toArray(), $table),
            'summary' => $this->pricing->summary($reader->view($storeProduct), $table),
        ]);
    }

    public function toggle(StoreToggleRequest $request, int $product): JsonResponse
    {
        $storeProduct = $this->catalog->find($product);
        $this->authorizeForUser($request->user('vendor'), 'update', $storeProduct);
        $featured = $request->routeIs('*.featured');

        $featured ? $this->catalog->setFeatured($storeProduct, $request->value()) : $this->catalog->setActive($storeProduct, $request->value());

        return JsonResponder::success((string) __($featured ? 'catalog::vendor.products.featured_saved' : 'catalog::vendor.products.active_saved'));
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $storeProduct = $this->catalog->find($product);
        $this->authorizeForUser($request->user('vendor'), 'delete', $storeProduct);

        return $this->attempt(fn () => $this->catalog->remove($storeProduct), (string) __('catalog::vendor.products.removed'));
    }

    /**
     * Shipping details modal (VEN-010e): fixed + zone fee and delivery window per store country.
     */
    public function shipping(int $product, ProductShippingDetailsService $shipping, StoreCatalogReader $reader): View
    {
        $storeProduct = $this->catalog->find($product);
        $central = $reader->view($storeProduct)->central ?? abort(404);

        return view('catalog::vendor.products._shipping', [
            'product' => $storeProduct,
            'rows' => $shipping->forProduct($central, null, $this->pricing->countriesFor($central)),
        ]);
    }
}
