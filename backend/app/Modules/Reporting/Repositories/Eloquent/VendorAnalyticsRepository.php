<?php

namespace App\Modules\Reporting\Repositories\Eloquent;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ShopperStatus;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\SqlDialect;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Store analytics over the central facts (`order_facts`, `product_daily_facts`,
 * `return_requests`, `refunds`), always on the central connection and always narrowed to
 * `$filters->tenantId`. Money is summed as recorded by Commerce\Pricing, never recomputed.
 *
 * @extends BaseRepository<OrderFact>
 */
class VendorAnalyticsRepository extends BaseRepository implements VendorAnalyticsRepositoryContract
{
    private const BOOKED = "status NOT IN ('pending_payment', 'cancelled')";

    protected function model(): string
    {
        return OrderFact::class;
    }

    public function orderTotals(ReportFilterData $filters): array
    {
        $row = $this->placed($filters)
            ->whereRaw(self::BOOKED)
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS gross,'
                .' COALESCE(SUM(CASE WHEN paid_at IS NOT NULL THEN grand_total ELSE 0 END), 0) AS collected,'
                .' COALESCE(SUM(CASE WHEN paid_at IS NULL THEN grand_total ELSE 0 END), 0) AS outstanding')
            ->first();

        return [
            'orders' => (int) ($row->orders ?? 0),
            'gross' => (int) ($row->gross ?? 0),
            'collected' => (int) ($row->collected ?? 0),
            'outstanding' => (int) ($row->outstanding ?? 0),
        ];
    }

    public function collectedDaily(ReportFilterData $filters): array
    {
        return $this->placed($filters)
            ->whereRaw(self::BOOKED)
            ->whereNotNull('paid_at')
            ->selectRaw('DATE(placed_at) AS day, SUM(grand_total) AS collected')
            ->groupByRaw('DATE(placed_at)')
            ->pluck('collected', 'day')
            ->mapWithKeys(static fn ($amount, $day): array => [(string) $day => (int) $amount])
            ->all();
    }

    public function monthly(ReportFilterData $filters): Builder
    {
        $month = SqlDialect::month('placed_at');
        $booked = self::BOOKED;

        $rows = $this->placed($filters)
            ->where('status', '!=', 'pending_payment')
            ->selectRaw("{$month} AS month, COUNT(*) AS orders,"
                ." SUM(CASE WHEN {$booked} AND paid_at IS NOT NULL THEN 1 ELSE 0 END) AS paid,"
                ." SUM(CASE WHEN {$booked} THEN 1 ELSE 0 END) AS booked,"
                ." SUM(CASE WHEN {$booked} THEN grand_total ELSE 0 END) AS gross,"
                ." SUM(CASE WHEN {$booked} AND paid_at IS NOT NULL THEN grand_total ELSE 0 END) AS collected")
            ->groupByRaw($month);

        return $this->db()->query()->fromSub($rows, 'm')
            ->selectRaw('m.month, m.orders, m.paid, m.booked, m.gross, m.collected, CASE WHEN m.booked > 0 THEN ROUND(m.gross / m.booked) ELSE 0 END AS avg_order');
    }

    public function customers(ReportFilterData $filters): Builder
    {
        $key = ReportFactRepository::CUSTOMER_KEY;

        $rows = $this->facts($filters)
            ->whereRaw(self::BOOKED)
            ->whereNotNull('placed_at')
            ->where('placed_at', '<=', $filters->end())
            ->whereRaw("{$key} IS NOT NULL")
            ->selectRaw("{$key} AS ckey, MAX(customer_name) AS customer_name, MAX(customer_email) AS customer_email, COUNT(*) AS orders,"
                .' SUM(CASE WHEN paid_at IS NOT NULL THEN 1 ELSE 0 END) AS paid, SUM(grand_total) AS spent,'
                .' SUM(CASE WHEN paid_at IS NOT NULL THEN grand_total ELSE 0 END) AS collected,'
                .' MIN(placed_at) AS first_order_at, MAX(placed_at) AS last_order_at')
            ->groupByRaw($key);

        return $this->db()->query()->fromSub($rows, 'c')
            ->selectRaw('c.*, CASE WHEN c.orders > 0 THEN ROUND(c.spent / c.orders) ELSE 0 END AS avg_order');
    }

    public function customerTotals(ReportFilterData $filters): array
    {
        $key = ReportFactRepository::CUSTOMER_KEY;
        $customers = (int) $this->facts($filters)
            ->where('status', '!=', 'pending_payment')
            ->whereNotNull('placed_at')
            ->where('placed_at', '<=', $filters->end())
            ->whereRaw("{$key} IS NOT NULL")
            ->selectRaw("COUNT(DISTINCT {$key}) AS total")
            ->value('total');

        $row = $this->db()->query()->fromSub($this->customers($filters), 't')
            ->selectRaw('COUNT(*) AS buyers, COALESCE(SUM(CASE WHEN orders > 1 THEN 1 ELSE 0 END), 0) AS repeat_buyers, COALESCE(SUM(spent), 0) AS spent')
            ->first();

        return [
            'customers' => $customers,
            'buyers' => (int) ($row->buyers ?? 0),
            'repeat' => (int) ($row->repeat_buyers ?? 0),
            'spent' => (int) ($row->spent ?? 0),
        ];
    }

    public function buyerMix(ReportFilterData $filters): array
    {
        $newSince = $filters->end()->startOfDay()->subDays(ShopperStatus::NEW_DAYS - 1);
        $activeSince = $filters->end()->startOfDay()->subDays(ShopperStatus::ACTIVE_DAYS - 1);

        $row = $this->db()->query()->fromSub($this->customers($filters), 't')
            ->selectRaw('COALESCE(SUM(CASE WHEN first_order_at >= ? THEN 1 ELSE 0 END), 0) AS new_buyers,'
                .' COALESCE(SUM(CASE WHEN first_order_at < ? AND last_order_at < ? THEN 1 ELSE 0 END), 0) AS inactive,'
                .' COALESCE(SUM(CASE WHEN first_order_at < ? AND last_order_at >= ? AND orders > 1 THEN 1 ELSE 0 END), 0) AS repeat_buyers,'
                .' COALESCE(SUM(CASE WHEN first_order_at < ? AND last_order_at >= ? AND orders = 1 THEN 1 ELSE 0 END), 0) AS one_time',
                [$newSince, $newSince, $activeSince, $newSince, $activeSince, $newSince, $activeSince])
            ->first();

        return [
            'new' => (int) ($row->new_buyers ?? 0),
            'repeat' => (int) ($row->repeat_buyers ?? 0),
            'one_time' => (int) ($row->one_time ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
        ];
    }

    public function shippingExtras(ReportFilterData $filters): array
    {
        $row = $this->facts($filters)
            ->whereNotNull('shipped_at')
            ->whereBetween('shipped_at', [$filters->start(), $filters->end()])
            ->selectRaw('COUNT(*) AS shipped, COALESCE(SUM(shipping_charge), 0) AS shipping,'
                .' COALESCE(SUM(CASE WHEN delivery_max_date IS NOT NULL AND ((delivered_at IS NOT NULL AND DATE(delivered_at) > delivery_max_date)'
                .' OR (delivered_at IS NULL AND delivery_max_date < ?)) THEN 1 ELSE 0 END), 0) AS delayed_orders', [now()->toDateString()])
            ->first();

        $shipped = (int) ($row->shipped ?? 0);

        return [
            'shipped' => $shipped,
            'avg_shipping' => $shipped > 0 ? (int) round((int) $row->shipping / $shipped) : 0,
            'delayed' => (int) ($row->delayed_orders ?? 0),
        ];
    }

    public function products(ReportFilterData $filters): Builder
    {
        $rows = $this->productDays($filters)
            ->where('units', '>', 0)
            ->selectRaw('store_product_id, MAX(product_name) AS product_name, SUM(orders) AS orders, SUM(units) AS units, SUM(revenue) AS revenue, SUM(profit) AS profit')
            ->groupBy('store_product_id');

        return $this->db()->query()->fromSub($rows, 'p')
            ->selectRaw('p.*, CASE WHEN p.revenue > 0 THEN ROUND(p.profit * 100.0 / p.revenue, 1) ELSE 0 END AS margin');
    }

    public function productTotals(ReportFilterData $filters): array
    {
        $row = $this->productDays($filters)
            ->selectRaw('COALESCE(SUM(units), 0) AS units, COALESCE(SUM(revenue), 0) AS revenue, COALESCE(SUM(profit), 0) AS profit')
            ->first();

        return ['units' => (int) ($row->units ?? 0), 'revenue' => (int) ($row->revenue ?? 0), 'profit' => (int) ($row->profit ?? 0)];
    }

    public function refunds(ReportFilterData $filters): Builder
    {
        return $this->db()->table('refunds')
            ->leftJoin('return_requests', 'return_requests.id', '=', 'refunds.return_request_id')
            ->where('refunds.tenant_id', $this->tenant($filters))
            ->whereBetween('refunds.requested_at', [$filters->start(), $filters->end()])
            ->select(['refunds.id', 'refunds.reference', 'refunds.order_number', 'refunds.source', 'refunds.amount', 'refunds.status', 'refunds.requested_at', 'return_requests.reference AS return_reference']);
    }

    public function returnStats(ReportFilterData $filters): array
    {
        $resolved = 'COALESCE(refunded_at, exchanged_at, closed_at)';
        $days = SqlDialect::daysBetween('created_at', $resolved);

        $row = $this->db()->table('return_requests')
            ->where('tenant_id', $this->tenant($filters))
            ->whereBetween('created_at', [$filters->start(), $filters->end()])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'exchange' THEN 1 ELSE 0 END), 0) AS exchanges,"
                ." AVG(CASE WHEN {$resolved} IS NOT NULL THEN {$days} END) AS avg_days")
            ->first();

        return [
            'exchanges' => (int) ($row->exchanges ?? 0),
            'avg_resolution_days' => round((float) ($row->avg_days ?? 0), 1),
        ];
    }

    /**
     * The store's order facts (any time).
     */
    private function facts(ReportFilterData $filters): Builder
    {
        return $this->db()->table('order_facts')->where('tenant_id', $this->tenant($filters));
    }

    /**
     * The store's orders placed inside the window.
     */
    private function placed(ReportFilterData $filters): Builder
    {
        return $this->facts($filters)->whereBetween('placed_at', [$filters->start(), $filters->end()]);
    }

    private function productDays(ReportFilterData $filters): Builder
    {
        return $this->db()->table('product_daily_facts')
            ->where('tenant_id', $this->tenant($filters))
            ->whereBetween('day', [$filters->start()->toDateString(), $filters->end()->toDateString()]);
    }

    private function tenant(ReportFilterData $filters): int
    {
        return $filters->tenantId ?? throw new InvalidArgumentException('Vendor analytics need the store (tenantId).');
    }

    private function db(): ConnectionInterface
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
