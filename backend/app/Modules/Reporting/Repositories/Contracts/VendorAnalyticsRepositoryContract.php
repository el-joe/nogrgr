<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Models\OrderFact;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Query\Builder;

/**
 * Read-only aggregates of the central Reporting facts for one store's analytics
 * (VEN-045 tabs, VEN-024 return analytics). Every method requires `$filters->tenantId`.
 * Booked = placed, not awaiting payment and not cancelled; collected = booked and paid.
 *
 * @extends RepositoryContract<OrderFact>
 */
interface VendorAnalyticsRepositoryContract extends RepositoryContract
{
    /**
     * @return array{orders: int, gross: int, collected: int, outstanding: int}
     */
    public function orderTotals(ReportFilterData $filters): array;

    /**
     * Collected amount (booked + paid) per placed day, keyed `Y-m-d`.
     *
     * @return array<string, int>
     */
    public function collectedDaily(ReportFilterData $filters): array;

    /**
     * Per month (`Y-m`): placed orders, paid orders, booked orders, gross, collected.
     */
    public function monthly(ReportFilterData $filters): Builder;

    /**
     * Lifetime values (booked orders placed up to the period end) per customer key.
     */
    public function customers(ReportFilterData $filters): Builder;

    /**
     * @return array{customers: int, buyers: int, repeat: int, spent: int}
     */
    public function customerTotals(ReportFilterData $filters): array;

    /**
     * Buyers as of the period end: new (first order ≤ 30 days), repeat (2+ orders),
     * one-time (1 order) and inactive (no order in 90 days) — disjoint, in that priority.
     *
     * @return array{new: int, repeat: int, one_time: int, inactive: int}
     */
    public function buyerMix(ReportFilterData $filters): array;

    /**
     * Orders shipped in the window: count, average shipping charge, delayed (delivered
     * after the promised date, or still undelivered past it).
     *
     * @return array{shipped: int, avg_shipping: int, delayed: int}
     */
    public function shippingExtras(ReportFilterData $filters): array;

    /**
     * Per store product: units, revenue, profit (store share) of paid orders.
     */
    public function products(ReportFilterData $filters): Builder;

    /**
     * @return array{units: int, revenue: int, profit: int}
     */
    public function productTotals(ReportFilterData $filters): array;

    /**
     * Refunds requested in the window (any status) with their return reference.
     */
    public function refunds(ReportFilterData $filters): Builder;

    /**
     * Return requests opened in the window: exchanges and average resolution days.
     *
     * @return array{exchanges: int, avg_resolution_days: float}
     */
    public function returnStats(ReportFilterData $filters): array;
}
