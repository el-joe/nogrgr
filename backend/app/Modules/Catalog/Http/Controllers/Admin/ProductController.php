<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\DataTables\ProductsDataTable;
use App\Modules\Catalog\DTOs\ProductData;
use App\Modules\Catalog\DTOs\ProductVariantData;
use App\Modules\Catalog\Http\Concerns\AnswersDomainErrors;
use App\Modules\Catalog\Http\Requests\Admin\ProductBulkRequest;
use App\Modules\Catalog\Http\Requests\Admin\ProductFieldRequest;
use App\Modules\Catalog\Http\Requests\Admin\ProductListRequest;
use App\Modules\Catalog\Http\Requests\Admin\ProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\TenantSearchRequest;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductExportService;
use App\Modules\Catalog\Services\ProductFormService;
use App\Modules\Catalog\Services\ProductPriceListService;
use App\Modules\Catalog\Services\ProductService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Catalog › Products (ADM-010 / 010b / 011, Figma 44:13636 / 65:29682 /
 * 46:14760 / 47:15543 / 65:28688): list with tabs and filters, delete / restore, CSV
 * export, price list modal, and the tabbed create / edit form with validate-on-blur.
 */
class ProductController extends Controller
{
    use AnswersDomainErrors;
    use RendersRemoteModals;

    public function __construct(
        private readonly ProductService $products,
        private readonly ProductFormService $form,
        private readonly ProductPriceListService $priceList,
        private readonly ProductExportService $export,
    ) {}

    public function index(ProductListRequest $request): View
    {
        return view('catalog::admin.products.index', [
            'tab' => $request->tab(),
            'counts' => $this->products->tabCounts(),
            ...$this->form->listOptions(),
        ]);
    }

    public function data(Request $request, ProductsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ProductsDataTable::FILTERS);
    }

    public function export(ProductListRequest $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request): void {
            $this->export->write(fopen('php://output', 'wb'), $request->filters());
        }, 'products-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        return view('catalog::admin.products.form', $this->form->viewData(new Product));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        try {
            $product = $this->products->create($this->productData($request));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('catalog::products.created', ['name' => $product->name]), redirect: route('admin.catalog.products.edit', $product));
    }

    public function edit(Product $product): View
    {
        return view('catalog::admin.products.form', $this->form->viewData($product));
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        return $this->attempt(
            fn () => $this->products->update($product, $this->productData($request)),
            __('catalog::products.updated', ['name' => $product->name]),
            route('admin.catalog.products.edit', $product),
        );
    }

    public function validateField(ProductFieldRequest $request): Response
    {
        return response()->noContent();
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return JsonResponder::success(__('catalog::products.deleted', ['name' => $product->name]));
    }

    public function bulk(ProductBulkRequest $request): JsonResponse
    {
        $data = $request->bulkData();
        $count = $this->products->bulk($data);

        return JsonResponder::success(trans_choice('catalog::products.bulk.done.'.$data->action->value, $count, ['count' => $count]), ['count' => $count]);
    }

    public function restore(int $product): JsonResponse
    {
        $model = $this->form->findWithTrashed($product);

        return $this->attempt(
            fn () => $this->products->restore($model),
            __('catalog::products.restored', ['name' => $model->name]),
        );
    }

    public function prices(Request $request, Product $product): View
    {
        $title = __('catalog::products.prices.title', ['name' => $product->name]);
        $index = route('admin.catalog.products.index');

        return $this->remoteModal($request, 'catalog::admin.products._price-list', ['product' => $product, 'rows' => $this->priceList->rows($product)], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            pageModule: 'admin-products',
            breadcrumbs: [__('navigation.sections.catalog') => null, __('catalog::products.title') => $index, $title => null],
        ));
    }

    public function pricesExport(Product $product): StreamedResponse
    {
        return response()->streamDownload(function () use ($product): void {
            $this->export->writePriceList(fopen('php://output', 'wb'), $this->priceList->rows($product));
        }, 'price-list-'.$product->sku.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function tenants(TenantSearchRequest $request): JsonResponse
    {
        return response()->json($this->form->tenantResults($request->validated('q'), (int) ($request->validated('page') ?? 1)));
    }

    private function productData(ProductRequest $request): ProductData
    {
        $payload = $request->payload();
        $payload['variants'] = array_map(static fn (array $row): ProductVariantData => ProductVariantData::fromArray($row), $payload['variants']);

        return ProductData::fromArray($payload);
    }
}
