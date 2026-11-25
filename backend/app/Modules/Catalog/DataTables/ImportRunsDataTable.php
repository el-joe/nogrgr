<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Import runs history (ADM-013, server-side). Filter: status.
 *
 * @extends BaseDataTable<CatalogImportRun>
 */
final class ImportRunsDataTable extends BaseDataTable
{
    public const FILTERS = ['status'];

    public function __construct(private readonly CatalogImportRepositoryContract $imports) {}

    protected function query(): Builder
    {
        return $this->imports->historyQuery()->select('catalog_import_runs.*');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = ImportStatus::tryFrom((string) ($filters['status'] ?? ''));

        return $query->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale();
        $number = static fn (int $value): string => '<bdi dir="ltr" class="tabular-nums">'.e(number_format($value)).'</bdi>';

        return $table
            ->filter(static fn (): null => null)
            ->editColumn('created_at', static fn (CatalogImportRun $run): string => view('catalog::admin.imports._run-cell', ['run' => $run, 'locale' => $locale])->render())
            ->editColumn('status', static fn (CatalogImportRun $run): string => '<span data-run-status>'.view('catalog::admin.imports._status', ['status' => $run->status])->render().'</span>')
            ->editColumn('created_count', static fn (CatalogImportRun $run): string => $number($run->created_count))
            ->editColumn('updated_count', static fn (CatalogImportRun $run): string => $number($run->updated_count))
            ->editColumn('skipped_count', static fn (CatalogImportRun $run): string => $number($run->skipped_count))
            ->editColumn('error_count', static fn (CatalogImportRun $run): string => $number($run->error_count))
            ->addColumn('actions', static fn (CatalogImportRun $run): string => view('catalog::admin.imports._run-actions', ['run' => $run])->render())
            ->rawColumns(['created_at', 'status', 'created_count', 'updated_count', 'skipped_count', 'error_count', 'actions']);
    }
}
