<?php

namespace App\Modules\Shipping\DataTables;

use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Shipping › Companies list (ADM-033): company + contact, countries (day rules),
 * default in, tracking URL, active switch. Filters: search, country, status.
 *
 * @extends BaseDataTable<ShippingCompany>
 */
final class ShippingCompaniesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'country_id', 'status'];

    public function __construct(private readonly ShippingCompanyRepositoryContract $companies) {}

    protected function query(): Builder
    {
        return $this->companies->query()->select('shipping_companies.*')->with(['dayRules.country', 'defaultCountries']);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $filters['status'] ?? null;
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($country !== false && $country !== null, fn (Builder $query) => $query->whereHas('dayRules', fn (Builder $query) => $query->where('country_id', $country)))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
                $query->where(fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(contact_email) LIKE ?', [$term]));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('name', $direction($order)))
            ->orderColumn('is_active', static fn (Builder $query, string $order) => $query->orderBy('is_active', $direction($order)))
            ->orderColumn('tracking_url_pattern', static fn (Builder $query, string $order) => $query->orderBy('tracking_url_pattern', $direction($order)))
            ->editColumn('name', fn (ShippingCompany $company): string => view('shipping::admin.companies._name-cell', ['company' => $company])->render())
            ->addColumn('countries', fn (ShippingCompany $company): string => e($company->dayRules->isEmpty() ? '—' : $company->dayRules->map(fn ($rule) => $rule->country->iso2)->implode(', ')))
            ->addColumn('default_in', fn (ShippingCompany $company): string => e($company->defaultCountries->isEmpty() ? '—' : $company->defaultCountries->pluck('iso2')->implode(', ')))
            ->editColumn('tracking_url_pattern', fn (ShippingCompany $company): string => '<span dir="ltr" class="text-ink-secondary">'.e($company->tracking_url_pattern !== null ? preg_replace('#^https?://(www\.)?#', '', $company->tracking_url_pattern) : '—').'</span>')
            ->editColumn('is_active', fn (ShippingCompany $company): string => view('shipping::admin.companies._active-cell', ['company' => $company])->render())
            ->addColumn('actions', fn (ShippingCompany $company): string => view('shipping::admin.companies._actions-cell', ['company' => $company])->render())
            ->rawColumns(['name', 'tracking_url_pattern', 'is_active', 'actions']);
    }
}
