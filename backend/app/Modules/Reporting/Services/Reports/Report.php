<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * One tab of Admin › Reports: the summary above the table, and the table itself (same
 * rows feed the server-side DataTable and the streamed CSV export).
 */
interface Report
{
    public function summary(ReportFilterData $filters): ReportSummary;

    public function tableTitle(): string;

    /**
     * @return list<ReportColumn>
     */
    public function columns(ReportFilterData $filters): array;

    /**
     * @return Builder|Collection<int, object>
     */
    public function rows(ReportFilterData $filters): Builder|Collection;

    /**
     * Initial table order: [column index, direction].
     *
     * @return array{0: int, 1: string}
     */
    public function defaultOrder(): array;
}
