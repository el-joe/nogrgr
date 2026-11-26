<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Models\OrderFact;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Read-only aggregates of `order_facts` for Admin › Reports, the dashboard, the vendor
 * "Stats & orders" tab and Shoppers. "Booked" orders = placed, not awaiting payment and
 * not cancelled, by `placed_at` inside the window.
 *
 * @extends RepositoryContract<OrderFact>
 */
interface ReportFactRepositoryContract extends RepositoryContract
{
    /**
     * @return Builder<OrderFact>
     */
    public function booked(ReportFilterData $filters): Builder;

    /**
     * @return array{orders: int, gross: int, units: int, platform: int, vendor: int, flash: int, coupon: int, coupon_orders: int, flash_orders: int, promo_revenue: int}
     */
    public function totals(ReportFilterData $filters): array;

    /**
     * Daily sums of booked orders keyed `Y-m-d`.
     *
     * @return array<string, array{orders: int, gross: int, units: int, platform: int, vendor: int, coupon: int, flash: int}>
     */
    public function daily(ReportFilterData $filters): array;

    /**
     * Placed orders (cancelled included) per status.
     *
     * @return array<string, int>
     */
    public function statusCounts(ReportFilterData $filters): array;

    /**
     * Gross sales per country id (0 = unknown).
     *
     * @return array<int, int>
     */
    public function grossByCountry(ReportFilterData $filters): array;

    /**
     * Per store: orders, gross, units, platform, vendor (+ refunded amount and returns).
     */
    public function byTenant(ReportFilterData $filters): QueryBuilder;

    /**
     * Per store and month: placed / delivered / cancelled orders and gross.
     */
    public function monthlyByTenant(ReportFilterData $filters): QueryBuilder;

    /**
     * Shipments by carrier and country: shipped, delivered, on time, average days.
     */
    public function shipments(ReportFilterData $filters): QueryBuilder;

    /**
     * @return array{shipped: int, delivered: int, on_time: int, avg_days: float}
     */
    public function shippingTotals(ReportFilterData $filters): array;

    /**
     * @return array<string, int> fulfilment status => shipped orders
     */
    public function fulfilmentCounts(ReportFilterData $filters): array;

    /**
     * Coupon campaigns: code, source, orders, discount, revenue.
     */
    public function coupons(ReportFilterData $filters): QueryBuilder;

    /**
     * Customers per store: buyers, new (first order in the window), returning.
     */
    public function customersByTenant(ReportFilterData $filters): QueryBuilder;

    /**
     * @return array{customers: int, new: int, returning: int}
     */
    public function customerTotals(ReportFilterData $filters): array;

    /**
     * New buyers per day (first booked order on that day), keyed `Y-m-d`.
     *
     * @return array<string, int>
     */
    public function newCustomersDaily(ReportFilterData $filters): array;

    /**
     * Distinct buyers per day, keyed `Y-m-d`.
     *
     * @return array<string, int>
     */
    public function buyersDaily(ReportFilterData $filters): array;

    /**
     * Latest placed orders (dashboard).
     *
     * @return Collection<int, OrderFact>
     */
    public function latest(ReportFilterData $filters, int $limit): Collection;

    /**
     * Placed orders of one store (vendor profile "Stats & orders").
     *
     * @return Builder<OrderFact>
     */
    public function ordersOf(int $tenantId): Builder;

    /**
     * Shoppers across stores (one row per store × customer key).
     *
     * @param  array{search?: string|null, status?: string|null, new_only?: bool}  $options
     */
    public function shoppers(ReportFilterData $filters, array $options = []): QueryBuilder;

    /**
     * @return array{shoppers: int, new: int, returning: int, orders: int}
     */
    public function shopperTotals(ReportFilterData $filters): array;
}
