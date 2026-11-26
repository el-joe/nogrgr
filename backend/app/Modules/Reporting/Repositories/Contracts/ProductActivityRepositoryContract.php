<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Models\ProductDailyFact;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Product performance facts (`product_daily_facts`): atomic increments + aggregates.
 *
 * @extends RepositoryContract<ProductDailyFact>
 */
interface ProductActivityRepositoryContract extends RepositoryContract
{
    /**
     * Adds to the counters of one store product on one day (created on first use).
     *
     * @param  array{views?: int, cart_adds?: int, orders?: int, units?: int, revenue?: int}  $increments
     */
    public function increment(int $tenantId, int $storeProductId, string $day, ?string $productName, array $increments): void;

    /**
     * @return array{views: int, cart_adds: int, orders: int, units: int, revenue: int}
     */
    public function totals(ReportFilterData $filters): array;

    /**
     * @return array<string, array{views: int, cart_adds: int, units: int}>
     */
    public function daily(ReportFilterData $filters): array;

    /**
     * Per store product: views, add-to-cart, orders, units, revenue.
     */
    public function byProduct(ReportFilterData $filters): QueryBuilder;
}
