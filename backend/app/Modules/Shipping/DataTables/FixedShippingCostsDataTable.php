<?php

namespace App\Modules\Shipping\DataTables;

use App\Modules\Shipping\Models\FixedShippingCost;
use App\Modules\Shipping\Repositories\Contracts\FixedShippingCostRepositoryContract;
use App\Modules\Shipping\Services\FixedShippingCostService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Shipping › Fixed costs list (ADM-031): country, rate per gram, 500 g example,
 * updated. Filter: search (country name / ISO).
 *
 * @extends BaseDataTable<FixedShippingCost>
 */
final class FixedShippingCostsDataTable extends BaseDataTable
{
    public const FILTERS = ['search'];

    public function __construct(
        private readonly FixedShippingCostRepositoryContract $costs,
        private readonly FixedShippingCostService $service,
    ) {}

    protected function query(): Builder
    {
        return $this->costs->query()->select('shipping_fixed_costs.*')->with('country');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query->when($search !== null, function (Builder $query) use ($search): void {
            $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
            $query->whereHas('country', static function (Builder $query) use ($term): void {
                $query->whereRaw('LOWER(iso2) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(iso3) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`countries`.`name`, '$.en'))) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`countries`.`name`, '$.ar'))) LIKE ?", [$term]);
            });
        });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('country', static fn (Builder $query, string $order) => $query->orderBy('country_id', $direction($order)))
            ->orderColumn('price_per_gram', static fn (Builder $query, string $order) => $query->orderBy('price_per_gram', $direction($order)))
            ->orderColumn('example', static fn (Builder $query, string $order) => $query->orderBy('price_per_gram', $direction($order)))
            ->orderColumn('updated_at', static fn (Builder $query, string $order) => $query->orderBy('updated_at', $direction($order)))
            ->addColumn('country', fn (FixedShippingCost $cost): string => view('shipping::admin.fixed-costs._country-cell', ['cost' => $cost])->render())
            ->editColumn('price_per_gram', fn (FixedShippingCost $cost): string => '<span dir="ltr" class="font-medium">$'.e(rtrim(rtrim((string) $cost->price_per_gram, '0'), '.') === '' ? '0' : self::rate((string) $cost->price_per_gram)).'</span>')
            ->addColumn('example', fn (FixedShippingCost $cost): string => '<span dir="ltr">'.e($this->service->shippingFor(FixedShippingCostService::EXAMPLE_WEIGHT_GRAMS, (string) $cost->price_per_gram)->format()).'</span>')
            ->editColumn('updated_at', fn (FixedShippingCost $cost): string => e($cost->updated_at?->diffForHumans() ?? '—'))
            ->addColumn('actions', fn (FixedShippingCost $cost): string => view('shipping::admin.fixed-costs._actions-cell', ['cost' => $cost])->render())
            ->rawColumns(['country', 'price_per_gram', 'example', 'actions']);
    }

    /**
     * Rate shown with at least 4 decimals (0.0040), trailing zeros beyond that trimmed.
     */
    public static function rate(string $rate): string
    {
        $formatted = rtrim(number_format((float) $rate, 6, '.', ''), '0');
        [$int, $dec] = array_pad(explode('.', $formatted), 2, '');

        return $int.'.'.str_pad($dec, 4, '0');
    }
}
