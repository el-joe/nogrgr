<?php

namespace App\Modules\Reporting\Repositories\Contracts;

use App\Modules\Reporting\Models\OrderFact;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<OrderFact>
 */
interface OrderFactRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertFor(int $tenantId, string $orderUuid, array $attributes): OrderFact;

    /**
     * Admin order list (ADM-020): tab / status, payment, gateway, tenant, country, dates, search.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<OrderFact>
     */
    public function listing(array $filters): Builder;

    /**
     * Shipping queue (ADM-022): catalog orders that are paid and not cancelled, by tab.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<OrderFact>
     */
    public function shippingQueue(array $filters): Builder;

    /**
     * @return array<string, int> status => count (placed orders)
     */
    public function statusCounts(): array;

    /**
     * @return array<string, int> queue tab => count
     */
    public function queueCounts(): array;

    public function findFact(int $id): ?OrderFact;
}
