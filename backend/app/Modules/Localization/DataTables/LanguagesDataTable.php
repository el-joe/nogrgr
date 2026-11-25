<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Localization\DataTables\Concerns\SearchesTranslations;
use App\Modules\Localization\Enums\ActiveState;
use App\Modules\Localization\Enums\TextDirection;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Localization › Languages list (server-side). Filters: status, direction,
 * search (code, native name, en/ar name).
 *
 * @extends BaseDataTable<Language>
 */
final class LanguagesDataTable extends BaseDataTable
{
    use SearchesTranslations;

    public const FILTERS = ['search', 'status', 'direction'];

    public function __construct(private readonly LanguageRepositoryContract $languages) {}

    protected function query(): Builder
    {
        return $this->languages->query()->select('languages.*');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = ActiveState::tryFrom((string) ($filters['status'] ?? ''));
        $direction = TextDirection::tryFrom((string) ($filters['direction'] ?? ''));
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('is_active', $status === ActiveState::Active))
            ->when($direction !== null, fn (Builder $query) => $query->where('direction', $direction?->value))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('code', 'like', $term)
                    ->orWhere('native_name', 'like', $term)
                    ->orWhere(fn (Builder $query) => $this->orWhereTranslationLike($query, 'name', $term)));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('name->'.$locale, $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('price', static fn (Builder $query, string $order) => $query->orderBy('price_minor', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('countries', static fn (Builder $query, string $order) => $query->orderBy('country_ids', $order === 'desc' ? 'desc' : 'asc'))
            ->editColumn('name', fn (Language $language): string => view('localization::admin.languages._name-cell', ['language' => $language])->render())
            ->editColumn('direction', fn (Language $language): string => view('localization::admin.languages._direction-cell', ['language' => $language])->render())
            ->addColumn('price', fn (Language $language): string => view('localization::admin.languages._price-cell', ['language' => $language])->render())
            ->addColumn('countries', fn (Language $language): string => e($language->country_ids === null || $language->country_ids === []
                ? __('localization::admin.languages.all_countries')
                : trans_choice('localization::admin.languages.countries_count', count($language->country_ids), ['count' => count($language->country_ids)])))
            ->editColumn('is_active', fn (Language $language): string => view('localization::admin.languages._status-cell', ['language' => $language])->render())
            ->addColumn('actions', fn (Language $language): string => view('localization::admin.languages._actions-cell', ['language' => $language])->render())
            ->rawColumns(['name', 'direction', 'price', 'countries', 'is_active', 'actions']);
    }
}
