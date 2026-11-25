<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Enums\ImportLogResult;
use App\Modules\Catalog\Models\CatalogImportLog;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Log of one import run (ADM-013, server-side). Filters: result (errors list =
 * result=error), search (item name / SKU / message).
 *
 * @extends BaseDataTable<CatalogImportLog>
 */
final class ImportLogsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'result'];

    private ?CatalogImportRun $run = null;

    public function __construct(private readonly CatalogImportRepositoryContract $imports) {}

    public function forRun(CatalogImportRun $run): self
    {
        $this->run = $run;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->run !== null
            ? $this->imports->logsQuery($this->run)->select('catalog_import_logs.*')
            : CatalogImportLog::query()->whereRaw('1 = 0');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $result = ImportLogResult::tryFrom((string) ($filters['result'] ?? ''));
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($result !== null, static fn (Builder $query) => $query->where('result', $result?->value))
            ->when($search !== null, static function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(static fn (Builder $query) => $query->where('item_name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('message', 'like', $term));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('id', $direction($order)))
            ->orderColumn('item', static fn (Builder $query, string $order) => $query->orderBy('item_name', $direction($order)))
            ->editColumn('created_at', static fn (CatalogImportLog $log): string => '<bdi dir="ltr" class="tabular-nums text-ink-secondary">'.e($log->created_at?->format('H:i:s') ?? '').'</bdi>')
            ->addColumn('item', static fn (CatalogImportLog $log): string => view('catalog::admin.imports._log-item', ['log' => $log])->render())
            ->editColumn('result', static fn (CatalogImportLog $log): string => view('catalog::admin.imports._result', ['result' => $log->result])->render())
            ->editColumn('message', static fn (CatalogImportLog $log): string => '<span class="text-ink">'.e($log->message).'</span>')
            ->rawColumns(['created_at', 'item', 'result', 'message']);
    }
}
