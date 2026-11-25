<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<OrderSettlement>
 */
class OrderSettlementRepository extends BaseRepository implements OrderSettlementRepositoryContract
{
    protected function model(): string
    {
        return OrderSettlement::class;
    }

    public function paidTotal(int $tenantId, string $orderUuid): int
    {
        return (int) OrderSettlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_uuid', $orderUuid)
            ->where('status', SettlementStatus::Paid->value)
            ->sum('amount');
    }

    public function forOrder(int $tenantId, string $orderUuid): Collection
    {
        return OrderSettlement::withoutTenantScope()->where('tenant_id', $tenantId)->where('order_uuid', $orderUuid)->orderByDesc('id')->get();
    }

    public function findByPaymentLog(int $paymentLogId): ?OrderSettlement
    {
        return OrderSettlement::withoutTenantScope()->where('payment_log_id', $paymentLogId)->first();
    }

    public function findForTenant(int $tenantId, int $id): ?OrderSettlement
    {
        return OrderSettlement::withoutTenantScope()->where('tenant_id', $tenantId)->whereKey($id)->first();
    }
}
