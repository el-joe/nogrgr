<?php

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Reporting\DataTables\ShoppersDataTable;
use App\Modules\Reporting\Enums\ShopperStatus;
use App\Modules\Reporting\Http\Requests\Admin\ShopperFilterRequest;
use App\Modules\Reporting\Services\ShopperService;
use App\Modules\Reporting\Services\StoreLookupService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Vendors › Shoppers (ADM-043, Figma 73:52854): read-only shoppers of every store
 * from central order facts, KPIs, store / country / status / new-only filters, CSV export.
 */
class ShopperController extends Controller
{
    public function __construct(
        private readonly ShopperService $shoppers,
        private readonly CountryService $countries,
    ) {}

    public function index(ShopperFilterRequest $request, StoreLookupService $stores): View
    {
        $filters = $request->filters();

        return view('reporting::admin.shoppers.index', [
            'kpis' => $this->shoppers->kpis($filters),
            'countries' => $this->countries->options(),
            'statuses' => ShopperStatus::options(),
            'selectedStore' => $stores->selected($filters->tenantId),
            'filters' => $filters,
        ]);
    }

    public function data(ShopperFilterRequest $request, ShoppersDataTable $table): JsonResponse
    {
        return $table->toJson($request->filters(), $request->options(), $this->countries->options());
    }

    public function export(ShopperFilterRequest $request): StreamedResponse
    {
        return $this->shoppers->export($request->filters(), $request->options(), $this->countries->options());
    }
}
