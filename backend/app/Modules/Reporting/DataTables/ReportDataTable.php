<?php

namespace App\Modules\Reporting\DataTables;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ReportTab;
use App\Modules\Reporting\Services\Reports\Report;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Reporting\Support\ReportCell;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\QueryDataTable;

/**
 * Server-side table of a report tab (Yajra): aggregated rows from the report, cells
 * rendered by ReportCell under `c_<key>`, ordering by the column's SQL alias (`name`).
 * The page's search box is not used: reports are narrowed by the page filters.
 */
final class ReportDataTable
{
    public function __construct(private readonly ReportService $reports) {}

    public function toJson(ReportTab $tab, ReportFilterData $filters): JsonResponse
    {
        $filters = $this->reports->filtersFor($tab, $filters);

        return $this->forReport($this->reports->report($tab), $filters);
    }

    /**
     * Table of any report (admin tabs, vendor analytics) for already-normalised filters.
     */
    public function forReport(Report $report, ReportFilterData $filters): JsonResponse
    {
        $rows = $report->rows($filters);

        /** @var DataTableAbstract $table */
        $table = $rows instanceof Builder ? new QueryDataTable($rows) : new CollectionDataTable($rows);
        $table->filter(static fn (): null => null, false);

        $raw = [];

        foreach ($report->columns($filters) as $column) {
            $table->addColumn('c_'.$column->key, static fn (object|array $row): string => ReportCell::html($column, (object) $row));
            $raw[] = 'c_'.$column->key;
        }

        return $table->rawColumns($raw)->toJson();
    }

    /**
     * Column specs for <x-ui::datatable>.
     *
     * @param  list<ReportColumn>  $columns
     * @return list<array<string, mixed>>
     */
    public static function columnSpecs(array $columns): array
    {
        return array_map(static fn (ReportColumn $column): array => array_filter([
            'data' => 'c_'.$column->key,
            'name' => $column->key,
            'title' => $column->label,
            'orderable' => $column->sortable,
            'searchable' => false,
            'align' => $column->alignEnd() ? 'end' : null,
        ], static fn ($value): bool => $value !== null), $columns);
    }
}
