<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\OrderSettlement;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * @extends RepositoryContract<OrderSettlement>
 */
interface OrderSettlementRepositoryContract extends RepositoryContract
{
    /**
     * Σ paid settlement principal of an order (USD minor).
     */
    public function paidTotal(int $tenantId, string $orderUuid): int;

    /**
     * @return Collection<int, OrderSettlement>
     */
    public function forOrder(int $tenantId, string $orderUuid): Collection;

    public function findByPaymentLog(int $paymentLogId): ?OrderSettlement;

    public function findForTenant(int $tenantId, int $id): ?OrderSettlement;

    /**
     * A new settlement reference (ST-…); a manual payment shares it across its orders.
     */
    public function nextReference(): string;

    /**
     * Settlements grouped by reference (one row per payment: orders count, amount,
     * status, gateway / method, dates). Filters: search, status, gateway, tenant_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<OrderSettlement>
     */
    public function grouped(array $filters = [], ?int $tenantId = null): Builder;

    /**
     * Rows of one settlement reference (optionally of one store).
     *
     * @return Collection<int, OrderSettlement>
     */
    public function byReference(string $reference, ?int $tenantId = null): Collection;

    /**
     * Counts per status (by reference) and the paid total of the last N days.
     *
     * @return array{pending: int, paid: int, failed: int, paid_recent: int}
     */
    public function stats(int $days = 30): array;

    /**
     * Last paid settlement date per store.
     *
     * @param  list<int>  $tenantIds
     * @return array<int, string>
     */
    public function lastPaidAt(array $tenantIds): array;

    /**
     * Paid settlements (all stores, or the given ones), oldest first — the ledger back-fill source.
     *
     * @param  list<int>|null  $tenantIds
     * @return LazyCollection<int, OrderSettlement>
     */
    public function paidCursor(?array $tenantIds = null): LazyCollection;
}
