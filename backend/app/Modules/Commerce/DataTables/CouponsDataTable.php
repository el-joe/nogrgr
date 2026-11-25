<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\Enums\CouponStatus;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Commerce\Support\PromotionFormatter;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Sales › Promotions › Coupons (ADM-025, Figma 70:39532): code + reach, discount,
 * min spend, usage (all stores), dates, derived status. Filters: country tab (coupons
 * usable there, incl. all-country ones), search, status, type.
 *
 * @extends BaseDataTable<Coupon>
 */
final class CouponsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'country_id', 'status', 'type'];

    public function __construct(private readonly CouponRepositoryContract $coupons) {}

    protected function query(): Builder
    {
        return $this->coupons->query()
            ->select('coupons.*')
            ->selectSub(self::usageSql(), 'usage_count')
            ->with(['countries:id,iso2', 'tenants:id,name']);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $status = CouponStatus::tryFrom((string) ($filters['status'] ?? ''));
        $type = CouponType::tryFrom((string) ($filters['type'] ?? ''));
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;
        $now = now();
        $usage = '('.self::usageSql()->toSql().')';

        return $query
            ->when($country !== false && $country !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereDoesntHave('countries')
                ->orWhereHas('countries', fn (Builder $countries) => $countries->whereKey($country))))
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type?->value))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
                $query->where(fn (Builder $inner) => $inner->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->when($status !== null, fn (Builder $query) => match ($status) {
                CouponStatus::Inactive => $query->where('is_active', false),
                CouponStatus::UsedUp => $query->where('is_active', true)->whereNotNull('usage_limit')->whereRaw("{$usage} >= usage_limit"),
                CouponStatus::Scheduled => $query->where('is_active', true)->where('starts_at', '>', $now)->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhereRaw("{$usage} < usage_limit")),
                CouponStatus::Expired => $query->where('is_active', true)->where('ends_at', '<', $now)->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhereRaw("{$usage} < usage_limit")),
                CouponStatus::Active => $query->where('is_active', true)
                    ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                    ->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhereRaw("{$usage} < usage_limit")),
                default => $query,
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('code', static fn (Builder $query, string $order) => $query->orderBy('code', $direction($order)))
            ->orderColumn('discount', static fn (Builder $query, string $order) => $query->orderBy('type', $direction($order))->orderBy('percentage', $direction($order))->orderBy('amount_minor', $direction($order)))
            ->orderColumn('min_spend', static fn (Builder $query, string $order) => $query->orderBy('min_spend_minor', $direction($order)))
            ->orderColumn('usage', static fn (Builder $query, string $order) => $query->orderBy('usage_count', $direction($order)))
            ->orderColumn('dates', static fn (Builder $query, string $order) => $query->orderBy('starts_at', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('is_active', $direction($order))->orderBy('ends_at', $direction($order)))
            ->editColumn('code', fn (Coupon $coupon): string => view('commerce::admin.coupons._code-cell', ['coupon' => $coupon])->render())
            ->addColumn('discount', fn (Coupon $coupon): string => e(PromotionFormatter::discount($coupon->type, $coupon->percentage, $coupon->amount_minor, $coupon->max_discount_minor)))
            ->addColumn('min_spend', fn (Coupon $coupon): string => '<span class="font-semibold text-ink" dir="ltr">'.e($coupon->minSpend()->format()).'</span>')
            ->addColumn('usage', fn (Coupon $coupon): string => '<span dir="ltr">'.e(PromotionFormatter::usage((int) $coupon->getAttribute('usage_count'), $coupon->usage_limit)).'</span>')
            ->addColumn('dates', fn (Coupon $coupon): string => e(PromotionFormatter::dates($coupon->starts_at, $coupon->ends_at)))
            ->addColumn('status', fn (Coupon $coupon): string => view('commerce::admin.partials._status-cell', ['status' => self::status($coupon)])->render())
            ->addColumn('actions', fn (Coupon $coupon): string => view('commerce::admin.coupons._actions-cell', ['coupon' => $coupon])->render())
            ->rawColumns(['code', 'min_spend', 'usage', 'status', 'actions']);
    }

    public static function status(Coupon $coupon): CouponStatus
    {
        $used = (int) $coupon->getAttribute('usage_count');
        $now = now();

        return match (true) {
            ! $coupon->is_active => CouponStatus::Inactive,
            $coupon->usage_limit !== null && $used >= $coupon->usage_limit => CouponStatus::UsedUp,
            $coupon->starts_at !== null && $coupon->starts_at->greaterThan($now) => CouponStatus::Scheduled,
            $coupon->ends_at !== null && $coupon->ends_at->lessThan($now) => CouponStatus::Expired,
            default => CouponStatus::Active,
        };
    }

    /**
     * Redemptions of the row's coupon across every store.
     */
    private static function usageSql(): \Illuminate\Database\Query\Builder
    {
        return DB::connection((new Coupon)->getConnectionName())
            ->table('coupon_redemptions')
            ->selectRaw('COUNT(*)')
            ->whereColumn('coupon_redemptions.coupon_id', 'coupons.id');
    }
}
