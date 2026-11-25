<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\DataTables\VendorsDataTable;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Exports\VendorsCsvExport;
use App\Modules\Platform\Services\VendorService;
use App\Shared\DataTables\DataTableFilters;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Vendors › All vendors (ADM-040): status tabs with counts, filters (search,
 * plan, country, status), server-side table and CSV export.
 * Permissions: vendors.view / vendors.export.
 */
class VendorController extends Controller
{
    public function __construct(
        private readonly VendorService $vendors,
        private readonly PlanRepositoryContract $plans,
        private readonly CountryService $countries,
    ) {}

    public function index(): View
    {
        return view('platform::admin.vendors.index', [
            'counts' => $this->vendors->tabCounts(),
            'statuses' => TenantStatus::listedOptions(),
            'plans' => $this->plans->options(),
            'countries' => $this->countries->options(),
        ]);
    }

    public function data(Request $request, VendorsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, VendorsDataTable::FILTERS);
    }

    public function export(Request $request, VendorsCsvExport $export): StreamedResponse
    {
        return $export->download(DataTableFilters::fromRequest($request, VendorsDataTable::FILTERS));
    }
}
