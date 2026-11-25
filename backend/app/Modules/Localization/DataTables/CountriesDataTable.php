<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Localization\DataTables\Concerns\SearchesTranslations;
use App\Modules\Localization\Enums\ActiveState;
use App\Modules\Localization\Enums\Continent;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Localization › Countries list (server-side). Filters: status, region
 * (continent), search (ISO codes, phone code, en/ar name).
 *
 * @extends BaseDataTable<Country>
 */
final class CountriesDataTable extends BaseDataTable
{
    use SearchesTranslations;

    public const FILTERS = ['search', 'status', 'continent'];

    public function __construct(private readonly CountryRepositoryContract $countries) {}

    protected function query(): Builder
    {
        return $this->countries->query()->select('countries.*');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = ActiveState::tryFrom((string) ($filters['status'] ?? ''));
        $continent = Continent::tryFrom((string) ($filters['continent'] ?? ''));
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('is_active', $status === ActiveState::Active))
            ->when($continent !== null, fn (Builder $query) => $query->where('continent', $continent?->value))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.addcslashes(ltrim((string) $search, '+'), '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('iso2', 'like', $term)
                    ->orWhere('iso3', 'like', $term)
                    ->orWhere('phone_code', 'like', $term)
                    ->orWhere('currency_code', 'like', $term)
                    ->orWhere(fn (Builder $query) => $this->orWhereTranslationLike($query, 'name', $term)));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('name->'.$locale, $order === 'desc' ? 'desc' : 'asc'))
            ->editColumn('name', fn (Country $country): string => view('localization::admin.countries._name-cell', ['country' => $country])->render())
            ->editColumn('currency_code', fn (Country $country): string => $country->currency_code !== null ? e($country->currency_code) : '<span class="text-ink-muted">—</span>')
            ->editColumn('phone_code', fn (Country $country): string => $country->phone_code !== null ? '<bdi dir="ltr">+'.e($country->phone_code).'</bdi>' : '<span class="text-ink-muted">—</span>')
            ->editColumn('continent', fn (Country $country): string => e($country->continent?->label() ?? '—'))
            ->editColumn('is_active', fn (Country $country): string => view('localization::admin.countries._status-cell', ['country' => $country])->render())
            ->addColumn('actions', fn (Country $country): string => view('localization::admin.countries._actions-cell', ['country' => $country])->render())
            ->rawColumns(['name', 'currency_code', 'phone_code', 'continent', 'is_active', 'actions']);
    }
}
