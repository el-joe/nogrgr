<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Enums\ReportTab;
use App\Modules\Reporting\Services\Reports\AffiliatesReport;
use App\Modules\Reporting\Services\Reports\CustomersReport;
use App\Modules\Reporting\Services\Reports\OrdersReport;
use App\Modules\Reporting\Services\Reports\ProductsReport;
use App\Modules\Reporting\Services\Reports\ProfitReport;
use App\Modules\Reporting\Services\Reports\PromotionsReport;
use App\Modules\Reporting\Services\Reports\RefundsReport;
use App\Modules\Reporting\Services\Reports\Report;
use App\Modules\Reporting\Services\Reports\SalesReport;
use App\Modules\Reporting\Services\Reports\ShippingReport;
use App\Modules\Reporting\Services\Reports\SubscriptionsReport;
use App\Modules\Reporting\Services\Reports\VendorsReport;
use App\Modules\Reporting\Support\ReportCell;
use App\Shared\Services\BaseService;
use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Reports (ADM-070): resolves a tab to its report, normalises the filters per
 * tab (platform-wide tabs ignore the country) and streams the CSV export.
 */
class ReportService extends BaseService
{
    private const REPORTS = [
        'sales' => SalesReport::class,
        'orders' => OrdersReport::class,
        'profit' => ProfitReport::class,
        'refunds' => RefundsReport::class,
        'shipping' => ShippingReport::class,
        'promotions' => PromotionsReport::class,
        'subscriptions' => SubscriptionsReport::class,
        'affiliates' => AffiliatesReport::class,
        'vendors' => VendorsReport::class,
        'products' => ProductsReport::class,
        'customers' => CustomersReport::class,
    ];

    public function report(ReportTab $tab): Report
    {
        return app(self::REPORTS[$tab->value]);
    }

    public function filtersFor(ReportTab $tab, ReportFilterData $filters): ReportFilterData
    {
        return $tab->filtersByCountry() ? $filters : $filters->with(['countryId' => null]);
    }

    public function summary(ReportTab $tab, ReportFilterData $filters): ReportSummary
    {
        return $this->report($tab)->summary($this->filtersFor($tab, $filters));
    }

    public function export(ReportTab $tab, ReportFilterData $filters): StreamedResponse
    {
        $filters = $this->filtersFor($tab, $filters);

        return $this->exportReport($this->report($tab), $filters, 'report-'.$tab->value);
    }

    /**
     * Streams the table of any report as CSV (UTF-8 BOM, raw USD decimals):
     * `<prefix>-<from>-<to>.csv`.
     */
    public function exportReport(Report $report, ReportFilterData $filters, string $prefix): StreamedResponse
    {
        $columns = $report->columns($filters);
        $rows = $report->rows($filters);
        $filename = $prefix.'-'.$filters->start()->toDateString().'-'.$filters->end()->toDateString().'.csv';

        return response()->streamDownload(function () use ($columns, $rows): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(static fn ($column): string => $column->label, $columns), escape: '\\');

            foreach ($rows instanceof Builder ? $rows->cursor() : $rows as $row) {
                fputcsv($out, array_map(static fn ($column): string => ReportCell::csv($column, $row), $columns), escape: '\\');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
