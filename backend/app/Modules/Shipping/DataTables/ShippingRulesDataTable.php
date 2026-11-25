<?php

namespace App\Modules\Shipping\DataTables;

use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Models\ShippingZoneBand;
use App\Modules\Shipping\Repositories\Contracts\ShippingRuleRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Shipping › Rules list (ADM-032): rule, countries, zones, bands, weight range,
 * status. Filters: search (name), country, status.
 *
 * @extends BaseDataTable<ShippingRule>
 */
final class ShippingRulesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'country_id', 'status'];

    public function __construct(private readonly ShippingRuleRepositoryContract $rules) {}

    protected function query(): Builder
    {
        return $this->rules->query()
            ->select('shipping_rules.*')
            ->with(['countries', 'zones.bands'])
            ->withCount('zones');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $filters['status'] ?? null;
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($country !== false && $country !== null, fn (Builder $query) => $query->whereHas('countries', fn (Builder $query) => $query->whereKey($country)))
            ->when(in_array($status, ShippingRuleStatus::values(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== null, fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%']));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('name', $direction($order)))
            ->orderColumn('zones_count', static fn (Builder $query, string $order) => $query->orderBy('zones_count', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->editColumn('name', fn (ShippingRule $rule): string => view('shipping::admin.rules._name-cell', ['rule' => $rule])->render())
            ->addColumn('countries', fn (ShippingRule $rule): string => e($rule->countries->count() === 1 ? (string) $rule->countries->first()?->name : $rule->countries->pluck('iso2')->implode(', ')))
            ->editColumn('zones_count', fn (ShippingRule $rule): string => '<span dir="ltr">'.(int) $rule->zones_count.'</span>')
            ->addColumn('bands', fn (ShippingRule $rule): string => '<span dir="ltr">'.$rule->zones->sum(fn (ShippingZone $zone): int => $zone->bands->count()).'</span>')
            ->addColumn('range', fn (ShippingRule $rule): string => '<span dir="ltr">'.e(self::range($rule)).'</span>')
            ->editColumn('status', fn (ShippingRule $rule): string => view('shipping::admin.rules._status-cell', ['rule' => $rule])->render())
            ->addColumn('actions', fn (ShippingRule $rule): string => view('shipping::admin.rules._actions-cell', ['rule' => $rule])->render())
            ->rawColumns(['name', 'zones_count', 'bands', 'range', 'status', 'actions']);
    }

    public static function range(ShippingRule $rule): string
    {
        $bands = $rule->zones->flatMap(fn (ShippingZone $zone) => $zone->bands);

        if ($bands->isEmpty()) {
            return '—';
        }

        $min = (int) $bands->min('min_weight_grams');
        $max = $bands->contains(fn (ShippingZoneBand $band): bool => $band->max_weight_grams === null) ? null : (int) $bands->max('max_weight_grams');

        return self::weight($min).' – '.($max === null ? '∞' : self::weight($max));
    }

    public static function weight(int $grams): string
    {
        return $grams >= 1000 && $grams % 1000 === 0 ? number_format($grams / 1000).' kg' : number_format($grams).' g';
    }
}
