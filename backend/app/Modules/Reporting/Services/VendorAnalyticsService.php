<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\DataTables\ReportDataTable;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\VendorAnalyticsTab;
use App\Modules\Reporting\Services\VendorAnalytics\ClvAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\OrdersAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\ProfitabilityAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\ReturnsAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\ShippingAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\VendorReport;
use App\Shared\Services\BaseService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor analytics (VEN-045 tabs, VEN-024 return analytics): resolves a tab to its report
 * and serves its server-side table and CSV export. Filters always carry the current store.
 */
class VendorAnalyticsService extends BaseService
{
    private const REPORTS = [
        'orders' => OrdersAnalytics::class,
        'clv' => ClvAnalytics::class,
        'shipping' => ShippingAnalytics::class,
        'profitability' => ProfitabilityAnalytics::class,
        'returns' => ReturnsAnalytics::class,
    ];

    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportDataTable $tables,
    ) {}

    public function report(VendorAnalyticsTab $tab): VendorReport
    {
        return app(self::REPORTS[$tab->value]);
    }

    public function table(VendorAnalyticsTab $tab, ReportFilterData $filters): JsonResponse
    {
        return $this->tables->forReport($this->report($tab), $filters);
    }

    public function export(VendorAnalyticsTab $tab, ReportFilterData $filters): StreamedResponse
    {
        return $this->reports->exportReport($this->report($tab), $filters, 'analytics-'.$tab->value);
    }
}
