<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Localization\DataTables\Concerns\SearchesTranslations;
use App\Modules\Localization\Enums\ActiveState;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Localization › Currencies list (server-side). Filters: status, search
 * (code, symbol, en/ar name). `countries_count` = countries using it as default.
 *
 * @extends BaseDataTable<Currency>
 */
final class CurrenciesDataTable extends BaseDataTable
{
    use SearchesTranslations;

    public const FILTERS = ['search', 'status'];

    public function __construct(private readonly CurrencyRepositoryContract $currencies) {}

    protected function query(): Builder
    {
        return $this->currencies->query()
            ->select('currencies.*')
            ->selectSub(Country::query()->selectRaw('count(*)')->whereColumn('countries.currency_code', 'currencies.code'), 'countries_count');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = ActiveState::tryFrom((string) ($filters['status'] ?? ''));
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('is_active', $status === ActiveState::Active))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('code', 'like', $term)
                    ->orWhere('symbol', 'like', $term)
                    ->orWhere(fn (Builder $query) => $this->orWhereTranslationLike($query, 'name', $term)));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('is_base', 'desc')->orderBy('name->'.$locale, $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('countries_count', static fn (Builder $query, string $order) => $query->orderBy('countries_count', $order === 'desc' ? 'desc' : 'asc'))
            ->editColumn('name', fn (Currency $currency): string => view('localization::admin.currencies._name-cell', ['currency' => $currency])->render())
            ->editColumn('symbol', fn (Currency $currency): string => '<bdi>'.e($currency->symbol).'</bdi>')
            ->editColumn('rate', fn (Currency $currency): string => '<bdi dir="ltr" class="font-medium tabular-nums">'.e(number_format((float) $currency->rate, 4)).'</bdi>')
            ->editColumn('countries_count', fn (Currency $currency): string => (int) $currency->getAttribute('countries_count') > 0
                ? e(trans_choice('localization::admin.languages.countries_count', (int) $currency->getAttribute('countries_count'), ['count' => (int) $currency->getAttribute('countries_count')]))
                : '<span class="text-ink-muted">—</span>')
            ->editColumn('rate_synced_at', fn (Currency $currency): string => $currency->rate_synced_at !== null
                ? '<time datetime="'.e($currency->rate_synced_at->toIso8601String()).'">'.e($currency->rate_synced_at->translatedFormat('d M Y, H:i')).'</time>'
                : '<span class="text-ink-muted">'.e(__('localization::admin.currencies.never_synced')).'</span>')
            ->editColumn('is_active', fn (Currency $currency): string => view('localization::admin.currencies._status-cell', ['currency' => $currency])->render())
            ->addColumn('actions', fn (Currency $currency): string => view('localization::admin.currencies._actions-cell', ['currency' => $currency])->render())
            ->rawColumns(['name', 'symbol', 'rate', 'countries_count', 'rate_synced_at', 'is_active', 'actions']);
    }
}
