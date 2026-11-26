<?php

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Reporting\DataTables\ReportDataTable;
use App\Modules\Reporting\Enums\ReportTab;
use App\Modules\Reporting\Http\Requests\Admin\ReportFilterRequest;
use App\Modules\Reporting\Http\Requests\Admin\StoreSearchRequest;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Reporting\Services\StoreLookupService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Reports (ADM-070 hub + tabs 070a…070k, Figma 75:68061 · 75:69019 · 75:69998):
 * period / store / country filters, KPIs, charts, server-side table and CSV export.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportFilterRequest $request): RedirectResponse
    {
        return redirect()->route('admin.reports.show', ['tab' => ReportTab::Sales->value, ...$request->filters()->query()]);
    }

    public function show(ReportFilterRequest $request, ReportTab $tab, CountryService $countries, StoreLookupService $stores): View
    {
        $filters = $this->reports->filtersFor($tab, $request->filters());
        $report = $this->reports->report($tab);

        return view('reporting::admin.reports.show', [
            'tab' => $tab,
            'filters' => $filters,
            'summary' => $report->summary($filters),
            'tableTitle' => $report->tableTitle(),
            'columns' => ReportDataTable::columnSpecs($report->columns($filters)),
            'order' => $report->defaultOrder(),
            'countries' => $countries->options(),
            'selectedStore' => $stores->selected($filters->tenantId),
        ]);
    }

    public function data(ReportFilterRequest $request, ReportTab $tab, ReportDataTable $table): JsonResponse
    {
        return $table->toJson($tab, $request->filters());
    }

    public function export(ReportFilterRequest $request, ReportTab $tab): StreamedResponse
    {
        return $this->reports->export($tab, $request->filters());
    }

    public function stores(StoreSearchRequest $request, StoreLookupService $stores): JsonResponse
    {
        return response()->json($stores->search($request->term(), $request->page()));
    }
}
