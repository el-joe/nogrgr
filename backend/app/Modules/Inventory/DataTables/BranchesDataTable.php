<?php

namespace App\Modules\Inventory\DataTables;

use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Logistics › Branches list (server-side): branch, country, SKUs in stock, units,
 * default-for countries, status. Filters: country, status, search (name / code).
 *
 * @extends BaseDataTable<Branch>
 */
final class BranchesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'country_id', 'status'];

    public function __construct(private readonly BranchRepositoryContract $branches) {}

    protected function query(): Builder
    {
        return $this->branches->query()
            ->select('branches.*')
            ->with(['country', 'city', 'defaultCountries'])
            ->withCount(['stock as skus_count' => static fn (Builder $query) => $query->where('on_hand', '>', 0)])
            ->withSum('stock as units_sum', 'on_hand');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $filters['status'] ?? null;
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($country !== false && $country !== null, fn (Builder $query) => $query->where('country_id', $country))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('LOWER(code) LIKE ?', [$term]);
                    foreach (['en', 'ar'] as $locale) {
                        $query->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`branches`.`name`, ?))) LIKE ?', ['$.'.$locale, $term]);
                    }
                });
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('name->'.$locale, $direction($order)))
            ->orderColumn('country', static fn (Builder $query, string $order) => $query->orderBy('country_id', $direction($order)))
            ->orderColumn('skus_count', static fn (Builder $query, string $order) => $query->orderBy('skus_count', $direction($order)))
            ->orderColumn('units_sum', static fn (Builder $query, string $order) => $query->orderBy('units_sum', $direction($order)))
            ->editColumn('name', fn (Branch $branch): string => view('inventory::admin.branches._name-cell', ['branch' => $branch])->render())
            ->addColumn('country', fn (Branch $branch): string => e(trim($branch->country->flag.' '.$branch->country->name)))
            ->editColumn('skus_count', fn (Branch $branch): string => '<span dir="ltr">'.e(number_format((int) $branch->skus_count)).'</span>')
            ->editColumn('units_sum', fn (Branch $branch): string => '<span dir="ltr" class="font-medium">'.e(number_format((int) $branch->units_sum)).'</span>')
            ->addColumn('default_for', fn (Branch $branch): string => e($branch->defaultCountries->isEmpty() ? '—' : $branch->defaultCountries->pluck('iso2')->implode(', ')))
            ->editColumn('is_active', fn (Branch $branch): string => view('inventory::admin.branches._status-cell', ['branch' => $branch])->render())
            ->addColumn('actions', fn (Branch $branch): string => view('inventory::admin.branches._actions-cell', ['branch' => $branch])->render())
            ->rawColumns(['name', 'skus_count', 'units_sum', 'is_active', 'actions']);
    }
}
