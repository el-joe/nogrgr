<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

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

    public function nextReference(): string
    {
        return 'ST-'.(3001 + (int) OrderSettlement::withoutTenantScope()->max('id')).'-'.strtoupper(Str::random(3));
    }

    public function grouped(array $filters = [], ?int $tenantId = null): Builder
    {
        $inner = OrderSettlement::withoutTenantScope()
            ->selectRaw('MIN(id) as id, reference, tenant_id, COUNT(*) as orders_count, SUM(amount) as amount, MAX(currency) as currency, MAX(gateway) as gateway, MAX(method) as method, MAX(status) as status, MAX(transaction_reference) as transaction_reference, MIN(created_at) as created_at, MAX(paid_at) as paid_at')
            ->when($tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->groupBy('reference', 'tenant_id');

        $query = OrderSettlement::withoutTenantScope()->fromSub($inner->toBase(), 'order_settlements');
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when(filled($filters['gateway'] ?? null), static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner->where('gateway', (string) $filters['gateway'])->orWhere('method', (string) $filters['gateway'])))
            ->when(filled($filters['tenant_id'] ?? null), static fn (Builder $q) => $q->where('tenant_id', (int) $filters['tenant_id']))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('reference', 'like', '%'.$search.'%')
                ->orWhere('transaction_reference', 'like', '%'.$search.'%')
                ->orWhereIn('tenant_id', Tenant::query()->where('name', 'like', '%'.$search.'%')->select('id'))));
    }

    public function byReference(string $reference, ?int $tenantId = null): Collection
    {
        return OrderSettlement::withoutTenantScope()
            ->where('reference', $reference)
            ->when($tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->get();
    }

    public function stats(int $days = 30): array
    {
        $counts = OrderSettlement::withoutTenantScope()
            ->selectRaw('status, COUNT(DISTINCT reference) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[SettlementStatus::Pending->value] ?? 0),
            'paid' => (int) ($counts[SettlementStatus::Paid->value] ?? 0),
            'failed' => (int) ($counts[SettlementStatus::Failed->value] ?? 0),
            'paid_recent' => (int) OrderSettlement::withoutTenantScope()->where('status', SettlementStatus::Paid->value)->where('paid_at', '>=', now()->subDays($days))->sum('amount'),
        ];
    }

    public function lastPaidAt(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        return OrderSettlement::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', SettlementStatus::Paid->value)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, MAX(paid_at) as last_paid')
            ->pluck('last_paid', 'tenant_id')
            ->map(static fn ($value): string => (string) $value)
            ->all();
    }
}
