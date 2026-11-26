<?php

namespace App\Modules\Reporting\Http\Controllers\Vendor;

use App\Modules\Reporting\DataTables\ReportDataTable;
use App\Modules\Reporting\Enums\VendorAnalyticsTab;
use App\Modules\Reporting\Http\Requests\Vendor\AnalyticsFilterRequest;
use App\Modules\Reporting\Services\VendorAnalyticsService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Analytics (VEN-045 + tabs 045a…d, Figma 12:16223 / 12:16815 / 87:108835) and
 * Orders › Return analytics (VEN-024, Figma 84:105181): period filter, KPIs, charts,
 * server-side table and CSV export of the current store's Reporting facts.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly VendorAnalyticsService $analytics) {}

    public function show(AnalyticsFilterRequest $request): View
    {
        $tab = VendorAnalyticsTab::tryFrom((string) $request->query('tab', ''));
        $tab = in_array($tab, VendorAnalyticsTab::analyticsTabs(), true) ? $tab : VendorAnalyticsTab::Orders;

        return $this->page('reporting::vendor.analytics', $tab, $request);
    }

    public function returns(AnalyticsFilterRequest $request): View
    {
        return $this->page('reporting::vendor.returns-analytics', VendorAnalyticsTab::Returns, $request);
    }

    public function data(AnalyticsFilterRequest $request, string $tab): JsonResponse
    {
        return $this->analytics->table($this->analyticsTab($tab), $request->filters());
    }

    public function export(AnalyticsFilterRequest $request, string $tab): StreamedResponse
    {
        return $this->analytics->export($this->analyticsTab($tab), $request->filters());
    }

    public function returnsData(AnalyticsFilterRequest $request): JsonResponse
    {
        return $this->analytics->table(VendorAnalyticsTab::Returns, $request->filters());
    }

    public function returnsExport(AnalyticsFilterRequest $request): StreamedResponse
    {
        return $this->analytics->export(VendorAnalyticsTab::Returns, $request->filters());
    }

    private function page(string $view, VendorAnalyticsTab $tab, AnalyticsFilterRequest $request): View
    {
        $filters = $request->filters();
        $report = $this->analytics->report($tab);

        return view($view, [
            'tab' => $tab,
            'filters' => $filters,
            'summary' => $report->summary($filters),
            'tableTitle' => $report->tableTitle(),
            'columns' => ReportDataTable::columnSpecs($report->columns($filters)),
            'order' => $report->defaultOrder(),
        ]);
    }

    private function analyticsTab(string $tab): VendorAnalyticsTab
    {
        $resolved = VendorAnalyticsTab::tryFrom($tab);
        abort_unless(in_array($resolved, VendorAnalyticsTab::analyticsTabs(), true), 404);

        return $resolved;
    }
}
