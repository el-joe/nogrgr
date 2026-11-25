<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\PayoutStatus;
use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<TenantPayout>
 */
class TenantPayoutRepository extends BaseRepository implements TenantPayoutRepositoryContract
{
    protected function model(): string
    {
        return TenantPayout::class;
    }

    public function listing(array $filters = [], ?int $tenantId = null): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return TenantPayout::withoutTenantScope()
            ->with('tenant')
            ->when($tenantId !== null, static fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->when(filled($filters['tenant_id'] ?? null), static fn (Builder $q) => $q->where('tenant_id', (int) $filters['tenant_id']))
            ->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when(filled($filters['method'] ?? null), static fn (Builder $q) => $q->where('method', (string) $filters['method']))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('reference', 'like', '%'.$search.'%')
                ->orWhere('transaction_reference', 'like', '%'.$search.'%')
                ->when($tenantId === null, static fn (Builder $any) => $any->orWhereIn('tenant_id', Tenant::query()->where('name', 'like', '%'.$search.'%')->select('id')))));
    }

    public function findForTenant(int $id, ?int $tenantId = null): ?TenantPayout
    {
        return TenantPayout::withoutTenantScope()
            ->with('tenant')
            ->whereKey($id)
            ->when($tenantId !== null, static fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    public function lock(TenantPayout $payout): TenantPayout
    {
        /** @var TenantPayout $locked */
        $locked = TenantPayout::withoutTenantScope()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    public function openTotal(?int $tenantId = null): int
    {
        return (int) TenantPayout::withoutTenantScope()
            ->whereIn('status', [PayoutStatus::Scheduled->value, PayoutStatus::Processing->value])
            ->when($tenantId !== null, static fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->sum('amount');
    }

    public function stats(): array
    {
        $counts = TenantPayout::withoutTenantScope()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'scheduled' => (int) ($counts[PayoutStatus::Scheduled->value] ?? 0),
            'processing' => (int) ($counts[PayoutStatus::Processing->value] ?? 0),
            'paid' => (int) ($counts[PayoutStatus::Paid->value] ?? 0),
            'cancelled' => (int) ($counts[PayoutStatus::Cancelled->value] ?? 0),
            'open_amount' => $this->openTotal(),
        ];
    }
}
