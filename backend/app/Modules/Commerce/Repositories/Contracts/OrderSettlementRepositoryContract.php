<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\OrderSettlement;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

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
}
