<?php

namespace App\Modules\AfterSales\Repositories\Contracts;

use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\RefundAttempt;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Refund>
 */
interface RefundRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Refund>
     */
    public function listing(array $filters): Builder;

    /**
     * @return array{refunded_30d: int, pending: int, failed: int, pending_count: int, failed_count: int, counts: array<string, int>}
     */
    public function stats(): array;

    public function findForTenant(int $id, ?int $tenantId = null): ?Refund;

    /**
     * @return Collection<int, Refund>
     */
    public function forOrder(int $tenantId, string $orderUuid): Collection;

    /**
     * Σ amount of the order's refunds in the given statuses.
     *
     * @param  list<string>  $statuses
     */
    public function sumForOrder(int $tenantId, string $orderUuid, array $statuses): int;

    /**
     * Σ refunds of several orders of a store in the given statuses (USD minor).
     *
     * @param  list<string>  $orderUuids
     * @param  list<string>  $statuses
     */
    public function sumForOrders(int $tenantId, array $orderUuids, array $statuses): int;

    /**
     * Σ refunds raised for one return in the given statuses (USD minor).
     *
     * @param  list<string>  $statuses
     */
    public function sumForReturn(int $returnId, array $statuses): int;

    public function nextReference(): string;

    public function lock(Refund $refund): Refund;

    /**
     * The refund a gateway reports, by its gateway refund id (refund or attempt log) or
     * our reference sent with the refund call (`<reference>-<attempt>`), all stores.
     */
    public function findByGatewayRefund(string $gateway, ?string $gatewayRefundId, ?string $reference): ?Refund;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function logAttempt(Refund $refund, array $attributes): RefundAttempt;
}
