<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ShopperStatus;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\SqlDialect;
use App\Shared\Repositories\BaseRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<OrderFact>
 */
class ReportFactRepository extends BaseRepository implements ReportFactRepositoryContract
{
    /** Statuses that never became a sale. */
    public const NOT_BOOKED = ['pending_payment', 'cancelled'];

    /** One shopper = the store's customer id, else the (lower-cased) e-mail of a guest. */
    public const CUSTOMER_KEY = 'COALESCE(CAST(customer_id AS CHAR), LOWER(customer_email))';

    protected function model(): string
    {
        return OrderFact::class;
    }

    public function query(): Builder
    {
        return OrderFact::withoutTenantScope();
    }

    public function booked(ReportFilterData $filters): Builder
    {
        return $this->scoped($filters)->whereNotIn('status', self::NOT_BOOKED);
    }

    public function totals(ReportFilterData $filters): array
    {
        $row = $this->booked($filters)->toBase()->selectRaw(
            'COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS gross, COALESCE(SUM(items_count), 0) AS units,'
            .' COALESCE(SUM(platform_profit), 0) AS platform, COALESCE(SUM(vendor_profit), 0) AS vendor,'
            .' COALESCE(SUM(flash_discount), 0) AS flash, COALESCE(SUM(coupon_discount), 0) AS coupon,'
            .' COALESCE(SUM(CASE WHEN coupon_discount > 0 THEN 1 ELSE 0 END), 0) AS coupon_orders,'
            .' COALESCE(SUM(CASE WHEN flash_discount > 0 THEN 1 ELSE 0 END), 0) AS flash_orders,'
            .' COALESCE(SUM(CASE WHEN coupon_discount > 0 OR flash_discount > 0 THEN grand_total ELSE 0 END), 0) AS promo_revenue'
        )->first();

        return array_map('intval', (array) $row) + ['orders' => 0];
    }

    public function daily(ReportFilterData $filters): array
    {
        return $this->booked($filters)->toBase()
            ->selectRaw('DATE(placed_at) AS day, COUNT(*) AS orders, SUM(grand_total) AS gross, SUM(items_count) AS units,'
                .' SUM(platform_profit) AS platform, SUM(vendor_profit) AS vendor, SUM(coupon_discount) AS coupon, SUM(flash_discount) AS flash')
            ->groupByRaw('DATE(placed_at)')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->day => [
                'orders' => (int) $row->orders,
                'gross' => (int) $row->gross,
                'units' => (int) $row->units,
                'platform' => (int) $row->platform,
                'vendor' => (int) $row->vendor,
                'coupon' => (int) $row->coupon,
                'flash' => (int) $row->flash,
            ]])
            ->all();
    }

    public function statusCounts(ReportFilterData $filters): array
    {
        return $this->scoped($filters)->where('status', '!=', 'pending_payment')->toBase()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    public function grossByCountry(ReportFilterData $filters): array
    {
        return $this->booked($filters)->toBase()
            ->selectRaw('COALESCE(country_id, 0) AS country, SUM(grand_total) AS gross')
            ->groupByRaw('COALESCE(country_id, 0)')
            ->pluck('gross', 'country')
            ->mapWithKeys(static fn ($gross, $country): array => [(int) $country => (int) $gross])
            ->all();
    }

    public function byTenant(ReportFilterData $filters): QueryBuilder
    {
        $refunds = $this->central()->table('refunds')
            ->selectRaw('tenant_id, SUM(amount) AS refunded, COUNT(*) AS refunds_count')
            ->where('status', 'completed')
            ->whereBetween('processed_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId))
            ->groupBy('tenant_id');

        $returns = $this->central()->table('return_requests')
            ->selectRaw('tenant_id, COUNT(*) AS returns_count, SUM(CASE WHEN seller_fault = 1 THEN 1 ELSE 0 END) AS seller_fault')
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->groupBy('tenant_id');

        $sales = $this->booked($filters)->toBase()
            ->selectRaw('tenant_id, COUNT(*) AS orders, SUM(grand_total) AS gross, SUM(items_count) AS units, SUM(platform_profit) AS platform, SUM(vendor_profit) AS vendor')
            ->groupBy('tenant_id');

        return $this->central()->query()->fromSub($sales, 's')
            ->join('tenants', 'tenants.id', '=', 's.tenant_id')
            ->leftJoinSub($refunds, 'r', 'r.tenant_id', '=', 's.tenant_id')
            ->leftJoinSub($returns, 'rt', 'rt.tenant_id', '=', 's.tenant_id')
            ->selectRaw('s.tenant_id, tenants.name AS tenant_name, tenants.slug AS tenant_slug, s.orders, s.gross, s.units, s.platform, s.vendor,'
                .' COALESCE(r.refunded, 0) AS refunded, COALESCE(r.refunds_count, 0) AS refunds_count, s.gross - COALESCE(r.refunded, 0) AS net,'
                .' COALESCE(rt.returns_count, 0) AS returns_count, COALESCE(rt.seller_fault, 0) AS seller_fault');
    }

    public function monthlyByTenant(ReportFilterData $filters): QueryBuilder
    {
        $month = SqlDialect::month('placed_at');

        $rows = $this->scoped($filters)->where('status', '!=', 'pending_payment')->toBase()
            ->selectRaw("tenant_id, {$month} AS month, COUNT(*) AS orders,"
                ." SUM(CASE WHEN status IN ('delivered', 'completed') THEN 1 ELSE 0 END) AS delivered,"
                ." SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,"
                ." SUM(CASE WHEN status <> 'cancelled' THEN grand_total ELSE 0 END) AS gross,"
                ." SUM(CASE WHEN status <> 'cancelled' THEN items_count ELSE 0 END) AS units")
            ->groupBy('tenant_id')
            ->groupByRaw($month);

        return $this->central()->query()->fromSub($rows, 'm')
            ->join('tenants', 'tenants.id', '=', 'm.tenant_id')
            ->selectRaw('m.tenant_id, m.month, tenants.name AS tenant_name, tenants.slug AS tenant_slug, m.orders, m.delivered, m.cancelled, m.gross, m.units');
    }

    public function shipments(ReportFilterData $filters): QueryBuilder
    {
        $days = SqlDialect::daysBetween('shipped_at', 'delivered_at');

        $rows = $this->shippedIn($filters)->toBase()
            ->selectRaw("COALESCE(shipping_company_name, '') AS company, COALESCE(country_id, 0) AS country_id, COUNT(*) AS shipments,"
                .' SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS delivered,'
                .' SUM(CASE WHEN delivered_at IS NOT NULL AND delivery_max_date IS NOT NULL AND DATE(delivered_at) <= delivery_max_date THEN 1 ELSE 0 END) AS on_time,'
                ." AVG(CASE WHEN delivered_at IS NOT NULL THEN {$days} END) AS avg_days,"
                .' SUM(weight_grams) AS weight')
            ->groupByRaw("COALESCE(shipping_company_name, ''), COALESCE(country_id, 0)");

        return $this->central()->query()->fromSub($rows, 'sh')->select('sh.*');
    }

    public function shippingTotals(ReportFilterData $filters): array
    {
        $days = SqlDialect::daysBetween('shipped_at', 'delivered_at');
        $row = $this->shippedIn($filters)->toBase()
            ->selectRaw('COUNT(*) AS shipped, SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS delivered,'
                .' SUM(CASE WHEN delivered_at IS NOT NULL AND delivery_max_date IS NOT NULL AND DATE(delivered_at) <= delivery_max_date THEN 1 ELSE 0 END) AS on_time,'
                ." AVG(CASE WHEN delivered_at IS NOT NULL THEN {$days} END) AS avg_days")
            ->first();

        return [
            'shipped' => (int) ($row->shipped ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'on_time' => (int) ($row->on_time ?? 0),
            'avg_days' => round((float) ($row->avg_days ?? 0), 1),
        ];
    }

    public function fulfilmentCounts(ReportFilterData $filters): array
    {
        return $this->shippedIn($filters)->toBase()
            ->selectRaw('fulfilment_status, COUNT(*) AS total')
            ->groupBy('fulfilment_status')
            ->pluck('total', 'fulfilment_status')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    public function coupons(ReportFilterData $filters): QueryBuilder
    {
        $rows = $this->booked($filters)->toBase()
            ->where('coupon_discount', '>', 0)
            ->selectRaw("COALESCE(coupon_code, '') AS code, COALESCE(coupon_source, '') AS source, COUNT(*) AS orders,"
                .' SUM(coupon_discount) AS discount, SUM(grand_total) AS revenue, COUNT(DISTINCT tenant_id) AS stores')
            ->groupByRaw("COALESCE(coupon_code, ''), COALESCE(coupon_source, '')");

        return $this->central()->query()->fromSub($rows, 'c')->select('c.*');
    }

    public function customersByTenant(ReportFilterData $filters): QueryBuilder
    {
        $buyers = $this->booked($filters)->toBase()
            ->whereRaw(self::CUSTOMER_KEY.' IS NOT NULL')
            ->selectRaw('tenant_id, '.self::CUSTOMER_KEY.' AS ckey, COUNT(*) AS orders, SUM(grand_total) AS spent')
            ->groupBy('tenant_id')
            ->groupByRaw(self::CUSTOMER_KEY);

        $rows = $this->central()->query()->fromSub($buyers, 'b')
            ->joinSub($this->firstOrders($filters), 'f', static fn (JoinClause $join) => $join->on('f.tenant_id', '=', 'b.tenant_id')->on('f.ckey', '=', 'b.ckey'))
            ->selectRaw('b.tenant_id, COUNT(*) AS customers, SUM(CASE WHEN f.first_at >= ? THEN 1 ELSE 0 END) AS new_customers, SUM(b.orders) AS orders, SUM(b.spent) AS spent', [$filters->start()])
            ->groupBy('b.tenant_id');

        return $this->central()->query()->fromSub($rows, 'c')
            ->join('tenants', 'tenants.id', '=', 'c.tenant_id')
            ->selectRaw('c.tenant_id, tenants.name AS tenant_name, tenants.slug AS tenant_slug, c.customers, c.new_customers, c.customers - c.new_customers AS returning_customers, c.orders, c.spent');
    }

    public function customerTotals(ReportFilterData $filters): array
    {
        $row = $this->central()->query()->fromSub($this->customersByTenant($filters), 't')
            ->selectRaw('COALESCE(SUM(customers), 0) AS customers, COALESCE(SUM(new_customers), 0) AS new_customers')
            ->first();

        $customers = (int) ($row->customers ?? 0);
        $new = (int) ($row->new_customers ?? 0);

        return ['customers' => $customers, 'new' => $new, 'returning' => $customers - $new];
    }

    public function newCustomersDaily(ReportFilterData $filters): array
    {
        return $this->central()->query()->fromSub($this->firstOrders($filters), 'f')
            ->whereBetween('f.first_at', [$filters->start(), $filters->end()])
            ->selectRaw('DATE(f.first_at) AS day, COUNT(*) AS total')
            ->groupByRaw('DATE(f.first_at)')
            ->pluck('total', 'day')
            ->mapWithKeys(static fn ($total, $day): array => [(string) $day => (int) $total])
            ->all();
    }

    public function buyersDaily(ReportFilterData $filters): array
    {
        $buyers = $this->booked($filters)->toBase()
            ->whereRaw(self::CUSTOMER_KEY.' IS NOT NULL')
            ->selectRaw('DATE(placed_at) AS day, tenant_id, '.self::CUSTOMER_KEY.' AS ckey')
            ->groupByRaw('DATE(placed_at), tenant_id, '.self::CUSTOMER_KEY);

        return $this->central()->query()->fromSub($buyers, 'b')
            ->selectRaw('b.day, COUNT(*) AS total')
            ->groupBy('b.day')
            ->pluck('total', 'day')
            ->mapWithKeys(static fn ($total, $day): array => [(string) $day => (int) $total])
            ->all();
    }

    public function latest(ReportFilterData $filters, int $limit): Collection
    {
        return $this->query()
            ->with('tenant')
            ->where('status', '!=', 'pending_payment')
            ->when($filters->tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (Builder $query) => $query->where('country_id', $filters->countryId))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function ordersOf(int $tenantId): Builder
    {
        return $this->query()->where('tenant_id', $tenantId)->where('status', '!=', 'pending_payment');
    }

    public function shoppers(ReportFilterData $filters, array $options = []): QueryBuilder
    {
        $now = CarbonImmutable::now();
        $rows = $this->query()->toBase()
            ->whereNotIn('status', self::NOT_BOOKED)
            ->whereNotNull('placed_at')
            ->whereRaw(self::CUSTOMER_KEY.' IS NOT NULL')
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (QueryBuilder $query) => $query->where('country_id', $filters->countryId))
            ->selectRaw('tenant_id, '.self::CUSTOMER_KEY.' AS ckey, MAX(customer_name) AS customer_name, MAX(customer_email) AS customer_email,'
                .' MAX(country_id) AS country_id, COUNT(*) AS orders, SUM(grand_total) AS spent, MIN(placed_at) AS first_order_at, MAX(placed_at) AS last_order_at')
            ->groupBy('tenant_id')
            ->groupByRaw(self::CUSTOMER_KEY);

        $newSince = $now->subDays(ShopperStatus::NEW_DAYS);
        $activeSince = $now->subDays(ShopperStatus::ACTIVE_DAYS);
        $search = trim((string) ($options['search'] ?? ''));
        $status = ShopperStatus::tryFrom((string) ($options['status'] ?? ''));

        return $this->central()->query()->fromSub($rows, 'sh')
            ->join('tenants', 'tenants.id', '=', 'sh.tenant_id')
            ->selectRaw('sh.*, tenants.name AS tenant_name, tenants.slug AS tenant_slug,'
                .' CASE WHEN sh.first_order_at >= ? THEN ? WHEN sh.last_order_at >= ? THEN ? ELSE ? END AS shopper_status',
                [$newSince, ShopperStatus::New->value, $activeSince, ShopperStatus::Active->value, ShopperStatus::Inactive->value])
            ->when($search !== '', static function (QueryBuilder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static fn (QueryBuilder $inner) => $inner->where('sh.customer_name', 'like', $like)->orWhere('sh.customer_email', 'like', $like)->orWhere('tenants.name', 'like', $like));
            })
            ->when(($options['new_only'] ?? false) || $status === ShopperStatus::New, static fn (QueryBuilder $query) => $query->where('sh.first_order_at', '>=', $newSince))
            ->when($status === ShopperStatus::Active, static fn (QueryBuilder $query) => $query->where('sh.first_order_at', '<', $newSince)->where('sh.last_order_at', '>=', $activeSince))
            ->when($status === ShopperStatus::Inactive, static fn (QueryBuilder $query) => $query->where('sh.last_order_at', '<', $activeSince));
    }

    public function shopperTotals(ReportFilterData $filters): array
    {
        $row = $this->central()->query()->fromSub($this->shoppers($filters), 't')
            ->selectRaw('COUNT(*) AS shoppers, COALESCE(SUM(CASE WHEN shopper_status = ? THEN 1 ELSE 0 END), 0) AS new_shoppers,'
                .' COALESCE(SUM(CASE WHEN orders > 1 THEN 1 ELSE 0 END), 0) AS returning_shoppers, COALESCE(SUM(orders), 0) AS orders', [ShopperStatus::New->value])
            ->first();

        return [
            'shoppers' => (int) ($row->shoppers ?? 0),
            'new' => (int) ($row->new_shoppers ?? 0),
            'returning' => (int) ($row->returning_shoppers ?? 0),
            'orders' => (int) ($row->orders ?? 0),
        ];
    }

    /**
     * Placed orders (any status but awaiting payment) of the window, store and country.
     *
     * @return Builder<OrderFact>
     */
    private function scoped(ReportFilterData $filters): Builder
    {
        return $this->query()
            ->whereBetween('placed_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (Builder $query) => $query->where('country_id', $filters->countryId));
    }

    /**
     * Orders shipped inside the window.
     *
     * @return Builder<OrderFact>
     */
    private function shippedIn(ReportFilterData $filters): Builder
    {
        return $this->query()
            ->whereNotNull('shipped_at')
            ->whereBetween('shipped_at', [$filters->start(), $filters->end()])
            ->when($filters->tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (Builder $query) => $query->where('country_id', $filters->countryId));
    }

    /**
     * First booked order of every buyer of the store(s), all time.
     */
    private function firstOrders(ReportFilterData $filters): QueryBuilder
    {
        return $this->query()->toBase()
            ->whereNotIn('status', self::NOT_BOOKED)
            ->whereNotNull('placed_at')
            ->whereRaw(self::CUSTOMER_KEY.' IS NOT NULL')
            ->when($filters->tenantId !== null, static fn (QueryBuilder $query) => $query->where('tenant_id', $filters->tenantId))
            ->when($filters->countryId !== null, static fn (QueryBuilder $query) => $query->where('country_id', $filters->countryId))
            ->selectRaw('tenant_id, '.self::CUSTOMER_KEY.' AS ckey, MIN(placed_at) AS first_at')
            ->groupBy('tenant_id')
            ->groupByRaw(self::CUSTOMER_KEY);
    }

    /**
     * Reporting tables are central: queries must not follow the tenant connection that the
     * vendor panel switches to.
     */
    private function central(): ConnectionInterface
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
