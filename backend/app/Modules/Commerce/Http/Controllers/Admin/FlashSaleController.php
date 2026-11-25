<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Commerce\DataTables\FlashSalesDataTable;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Http\Requests\Admin\FlashSaleRequest;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Http\Requests\Admin\ToggleActiveRequest;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Services\Promotions\FlashSaleService;
use App\Modules\Commerce\Services\Promotions\PromotionPickerService;
use App\Modules\Localization\Services\CountryService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Sales › Promotions › Flash sales (ADM-026 / 026a, Figma 70:40462 / 70:41386):
 * Live / Scheduled / Ended tabs over a server-side list; view / create / edit in a modal
 * with the central product picker; active switch; delete.
 */
class FlashSaleController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly FlashSaleService $sales,
        private readonly PromotionPickerService $picker,
        private readonly CountryService $countries,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(Request $request): View
    {
        $phase = in_array($request->query('phase'), FlashSalesDataTable::PHASES, true) ? (string) $request->query('phase') : 'live';

        return view('commerce::admin.flash-sales.index', [
            'phase' => $phase,
            'counts' => $this->sales->tabCounts(),
            'countries' => $this->countries->options(),
        ]);
    }

    public function data(Request $request, FlashSalesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, FlashSalesDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new FlashSale(['is_active' => true, 'store_scope' => PromotionScope::AllStores, 'discount_percentage' => null]), true);
    }

    public function store(FlashSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->toData());

        return JsonResponder::created(__('commerce::flash_sales.created', ['title' => $sale->title]), redirect: route('admin.promotions.flash-sales.index'));
    }

    public function show(Request $request, FlashSale $flashSale): View
    {
        return $this->form($request, $flashSale, false);
    }

    public function edit(Request $request, FlashSale $flashSale): View
    {
        return $this->form($request, $flashSale, true);
    }

    public function update(FlashSaleRequest $request, FlashSale $flashSale): JsonResponse
    {
        $this->sales->update($flashSale, $request->toData());

        return JsonResponder::success(__('commerce::flash_sales.updated', ['title' => $flashSale->title]));
    }

    public function toggle(ToggleActiveRequest $request, FlashSale $flashSale): JsonResponse
    {
        $this->sales->setActive($flashSale, $request->value());

        return JsonResponder::success(__($request->value() ? 'commerce::flash_sales.activated' : 'commerce::flash_sales.deactivated', ['title' => $flashSale->title]));
    }

    public function destroy(FlashSale $flashSale): JsonResponse
    {
        $this->sales->delete($flashSale);

        return JsonResponder::success(__('commerce::flash_sales.deleted', ['title' => $flashSale->title]));
    }

    public function products(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->picker->products($request->term(), $request->page()));
    }

    public function stores(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->picker->stores($request->term(), $request->page()));
    }

    private function form(Request $request, FlashSale $sale, bool $editable): View
    {
        $title = match (true) {
            ! $sale->exists => __('commerce::flash_sales.create_title'),
            $editable => __('commerce::flash_sales.edit_title', ['title' => $sale->title]),
            default => (string) $sale->title,
        };
        $index = route('admin.promotions.flash-sales.index');
        $sale->loadMissing(['countries:id', 'tenants:id,name', 'products']);

        return $this->remoteModal($request, 'commerce::admin.flash-sales._form', [
            'sale' => $sale,
            'title' => $title,
            'editable' => $editable,
            'locales' => $this->locales->all(),
            'countries' => $this->countries->options(),
            'scopes' => PromotionScope::options(),
            'products' => $sale->products->map(static fn ($product): array => PromotionPickerService::productOption($product))->all(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            formId: $editable ? 'flash-sale-form' : null,
            submitLabel: __('commerce::promotions.save'),
            breadcrumbs: [__('navigation.sections.sales') => null, __('navigation.admin.promotions') => null, __('commerce::flash_sales.title') => $index, $title => null],
        ));
    }
}
