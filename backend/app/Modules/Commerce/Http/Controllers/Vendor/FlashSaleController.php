<?php

namespace App\Modules\Commerce\Http\Controllers\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Commerce\DataTables\VendorFlashSalesDataTable;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\FlashSaleStatus;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Http\Requests\Vendor\AttachFlashSaleProductRequest;
use App\Modules\Commerce\Http\Requests\Vendor\PromotionVisibilityRequest;
use App\Modules\Commerce\Http\Requests\Vendor\StoreFlashSaleRequest;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Models\StoreFlashSaleProduct;
use App\Modules\Commerce\Services\Promotions\CentralPromotionVisibilityService;
use App\Modules\Commerce\Services\Promotions\StoreFlashSaleService;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Ads & Marketing › Flash sales (VEN-041 / 041a / 041b, Figma 87:108154 /
 * 87:111915): "My flash sales" (store-funded; create / view / edit / delete in a modal
 * with the store product picker and banner) and "Nogrgr flash sales" (central, read-only,
 * hide / show), plus "Add to flash sale" from a product (VEN-041b).
 */
class FlashSaleController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly StoreFlashSaleService $sales,
        private readonly VendorPromotionPageService $pages,
        private readonly CentralPromotionVisibilityService $visibility,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(Request $request): View
    {
        return view('commerce::vendor.flash-sales.index', [
            'tab' => VendorPromotionPageService::tab($request->query('tab')),
            'counts' => $this->pages->flashSaleCounts(),
            'statuses' => FlashSaleStatus::options(),
            'centralTitles' => $this->pages->runningCentralSaleTitles(),
        ]);
    }

    public function data(Request $request, VendorFlashSalesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, $this->canManage($request));
    }

    public function create(Request $request): View
    {
        return $this->form($request, new StoreFlashSale(['is_active' => true]), true);
    }

    public function store(StoreFlashSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->toData());

        return JsonResponder::created(__('commerce::flash_sales.created', ['title' => $sale->title]), redirect: vendor_route('marketing.flash-sales.index'));
    }

    public function show(Request $request, int $flashSale): View
    {
        return $this->form($request, $this->pages->findSale($flashSale), false);
    }

    public function edit(Request $request, int $flashSale): View
    {
        return $this->form($request, $this->pages->findSale($flashSale), true);
    }

    public function update(StoreFlashSaleRequest $request, int $flashSale): JsonResponse
    {
        $sale = $this->sales->update($this->pages->findSale($flashSale), $request->toData());

        return JsonResponder::success(__('commerce::flash_sales.updated', ['title' => $sale->title]));
    }

    public function destroy(int $flashSale): JsonResponse
    {
        $sale = $this->pages->findSale($flashSale);
        $this->sales->delete($sale);

        return JsonResponder::success(__('commerce::flash_sales.deleted', ['title' => $sale->title]));
    }

    public function visibility(PromotionVisibilityRequest $request, int $flashSale): JsonResponse
    {
        $central = $this->pages->centralFlashSale($flashSale);
        abort_if($central === null, 404);

        $this->visibility->setHidden(PromotionKind::FlashSale, $central->id, $request->hidden());

        return JsonResponder::success(__($request->hidden() ? 'commerce::vendor_promotions.hidden_done' : 'commerce::vendor_promotions.shown_done', ['name' => $central->title]));
    }

    public function products(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->pages->searchProducts($request->term(), $request->page()));
    }

    /**
     * VEN-041b modal: the store's sales that have not ended, as radios.
     */
    public function availableForProduct(Request $request, int $product): View
    {
        $row = $this->pages->findProduct($product);
        $ref = $this->pages->ref($row);
        $title = __('commerce::vendor_promotions.flash_sales.attach.title');
        $index = vendor_route('marketing.flash-sales.index');

        return $this->remoteModal($request, 'commerce::vendor.flash-sales._attach-form', [
            'product' => $row,
            'productLabel' => $this->pages->label($row),
            'sales' => $this->pages->openSales(),
            'ref' => $ref,
            'title' => $title,
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-catalog',
            formId: 'flash-sale-attach-form',
            submitLabel: __('commerce::promotions.save'),
            breadcrumbs: [__('commerce::vendor_promotions.section') => null, __('commerce::flash_sales.title') => $index, $title => null],
        ));
    }

    public function attachProduct(AttachFlashSaleProductRequest $request, int $product): JsonResponse
    {
        $row = $this->pages->findProduct($product);
        $sale = $this->pages->findSale($request->saleId());
        $label = $this->pages->label($row);
        $attached = $this->sales->attachProduct($sale, $this->pages->ref($row));

        return JsonResponder::success(__($attached ? 'commerce::vendor_promotions.flash_sales.attach.done' : 'commerce::vendor_promotions.flash_sales.attach.already', ['product' => $label, 'sale' => $sale->title]));
    }

    private function form(Request $request, StoreFlashSale $sale, bool $editable): View
    {
        $title = match (true) {
            ! $sale->exists => __('commerce::flash_sales.create_title'),
            $editable => __('commerce::flash_sales.edit_title', ['title' => $sale->title]),
            default => (string) $sale->title,
        };
        $index = vendor_route('marketing.flash-sales.index');
        $sale->loadMissing(['productRows', 'countryRows']);
        $refs = $sale->productRows->map(static fn (StoreFlashSaleProduct $row): ProductRef => new ProductRef($row->product_source, $row->product_id))->values()->all();

        return $this->remoteModal($request, 'commerce::vendor.flash-sales._form', [
            'sale' => $sale,
            'title' => $title,
            'editable' => $editable,
            'locales' => $this->locales->all(),
            'countries' => $this->pages->countries(),
            'products' => $this->pages->productOptions($refs),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-catalog',
            formId: $editable ? 'vendor-flash-sale-form' : null,
            submitLabel: __('commerce::promotions.save'),
            breadcrumbs: [__('commerce::vendor_promotions.section') => null, __('commerce::flash_sales.title') => $index, $title => null],
        ));
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('flash-sales.manage');
    }
}
