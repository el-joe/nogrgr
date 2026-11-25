<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\Enums\FlashSaleStatus;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Repositories\Contracts\FlashSaleRepositoryContract;
use App\Modules\Commerce\Support\PromotionFormatter;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Sales › Promotions › Flash sales (ADM-026, Figma 70:40462): tabs Live /
 * Scheduled / Ended (`phase`), title + banner, discount, products, countries, ends,
 * status. Filters: search (title), country (sales running there, incl. all-country
 * ones), status (on / off).
 *
 * @extends BaseDataTable<FlashSale>
 */
final class FlashSalesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'phase', 'country_id', 'status'];

    public const PHASES = ['live', 'scheduled', 'ended'];

    public function __construct(private readonly FlashSaleRepositoryContract $sales) {}

    protected function query(): Builder
    {
        return $this->sales->query()->select('flash_sales.*')->withCount('products')->with('countries:id,iso2');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $phase = in_array($filters['phase'] ?? null, self::PHASES, true) ? (string) $filters['phase'] : 'live';
        $status = $filters['status'] ?? null;
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;
        $now = now();

        return $query
            ->when($phase === 'live', fn (Builder $query) => $query->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now))
            ->when($phase === 'scheduled', fn (Builder $query) => $query->where('starts_at', '>', $now))
            ->when($phase === 'ended', fn (Builder $query) => $query->where('ends_at', '<', $now))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($country !== false && $country !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereDoesntHave('countries')
                ->orWhereHas('countries', fn (Builder $countries) => $countries->whereKey($country))))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`title`, "$.en"))) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`title`, "$.ar"))) LIKE ?', [$term]));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('title', static fn (Builder $query, string $order) => $query->orderByRaw('JSON_UNQUOTE(JSON_EXTRACT(`title`, "$.'.(app()->getLocale() === 'ar' ? 'ar' : 'en').'")) '.$direction($order)))
            ->orderColumn('discount_percentage', static fn (Builder $query, string $order) => $query->orderBy('discount_percentage', $direction($order)))
            ->orderColumn('products_count', static fn (Builder $query, string $order) => $query->orderBy('products_count', $direction($order)))
            ->orderColumn('ends_at', static fn (Builder $query, string $order) => $query->orderBy('ends_at', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('is_active', $direction($order))->orderBy('starts_at', $direction($order)))
            ->editColumn('title', fn (FlashSale $sale): string => view('commerce::admin.flash-sales._title-cell', ['sale' => $sale])->render())
            ->editColumn('discount_percentage', fn (FlashSale $sale): string => e(PromotionFormatter::percent($sale->discount_percentage)))
            ->editColumn('products_count', fn (FlashSale $sale): string => '<span class="font-semibold text-ink" dir="ltr">'.number_format((int) $sale->products_count).'</span>')
            ->addColumn('countries', fn (FlashSale $sale): string => e($sale->countries->isEmpty() ? __('commerce::promotions.all') : $sale->countries->pluck('iso2')->implode(', ')))
            ->editColumn('ends_at', fn (FlashSale $sale): string => e(self::status($sale) === FlashSaleStatus::Live ? PromotionFormatter::endsIn($sale->ends_at) : $sale->ends_at->locale(app()->getLocale())->isoFormat('DD MMM YYYY HH:mm')))
            ->addColumn('status', fn (FlashSale $sale): string => view('commerce::admin.partials._status-cell', ['status' => self::status($sale)])->render())
            ->addColumn('actions', fn (FlashSale $sale): string => view('commerce::admin.flash-sales._actions-cell', ['sale' => $sale])->render())
            ->rawColumns(['title', 'products_count', 'status', 'actions']);
    }

    public static function status(FlashSale $sale): FlashSaleStatus
    {
        $now = now();

        return match (true) {
            ! $sale->is_active => FlashSaleStatus::Inactive,
            $sale->starts_at->greaterThan($now) => FlashSaleStatus::Scheduled,
            $sale->ends_at->lessThan($now) => FlashSaleStatus::Ended,
            default => FlashSaleStatus::Live,
        };
    }
}
