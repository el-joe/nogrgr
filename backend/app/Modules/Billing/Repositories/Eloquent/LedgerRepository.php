<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Models\LedgerEntry;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<LedgerTransaction>
 */
class LedgerRepository extends BaseRepository implements LedgerRepositoryContract
{
    protected function model(): string
    {
        return LedgerTransaction::class;
    }

    public function findByKey(string $idempotencyKey): ?LedgerTransaction
    {
        return LedgerTransaction::withoutTenantScope()->where('idempotency_key', $idempotencyKey)->first();
    }

    public function lockedBalance(int $tenantId): int
    {
        Tenant::query()->whereKey($tenantId)->lockForUpdate()->value('id');

        return (int) LedgerTransaction::withoutTenantScope()->where('tenant_id', $tenantId)->orderByDesc('id')->value('balance_after');
    }

    public function record(array $attributes, array $entries): LedgerTransaction
    {
        /** @var LedgerTransaction $transaction */
        $transaction = LedgerTransaction::withoutTenantScope()->create($attributes);
        $transaction->forceFill(['reference' => 'TX-'.(88000 + $transaction->id)])->save();
        $transaction->entries()->createMany($entries);

        return $transaction;
    }

    public function accountBalance(int $tenantId, LedgerAccount $account, ?string $orderUuid = null, ?array $sources = null): int
    {
        $query = LedgerEntry::withoutTenantScope()
            ->where('ledger_entries.tenant_id', $tenantId)
            ->where('account', $account->value)
            ->when($orderUuid !== null, static fn (Builder $q) => $q->where('ledger_entries.order_uuid', $orderUuid))
            ->when($sources !== null, static fn (Builder $q) => $q->whereIn('ledger_transaction_id', LedgerTransaction::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->whereIn('source', array_map(static fn (LedgerSource $source): string => $source->value, $sources ?? []))
                ->select('id')));

        return (int) $query->sum('debit') - (int) $query->sum('credit');
    }

    public function openReceivables(int $tenantId): Collection
    {
        return LedgerEntry::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('account', LedgerAccount::VendorReceivable->value)
            ->whereNotNull('order_uuid')
            ->groupBy('order_uuid')
            ->selectRaw('order_uuid, MAX(order_number) as order_number, SUM(debit) - SUM(credit) as owed, MIN(created_at) as since')
            ->havingRaw('SUM(debit) - SUM(credit) > 0')
            ->orderBy('since')
            ->toBase()
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->order_uuid => [
                'uuid' => (string) $row->order_uuid,
                'number' => $row->order_number !== null ? (string) $row->order_number : null,
                'owed' => (int) $row->owed,
                'since' => (string) $row->since,
            ]]);
    }

    public function balances(?array $tenantIds = null): array
    {
        $rows = LedgerEntry::withoutTenantScope()
            ->whereIn('account', [LedgerAccount::VendorPayable->value, LedgerAccount::VendorReceivable->value])
            ->when($tenantIds !== null, static fn (Builder $q) => $q->whereIn('tenant_id', $tenantIds ?? []))
            ->groupBy('tenant_id', 'account')
            ->selectRaw('tenant_id, account, SUM(debit) as debit, SUM(credit) as credit')
            ->toBase()
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $tenant = (int) $row->tenant_id;
            $balances[$tenant] ??= ['payable' => 0, 'receivable' => 0];

            if ($row->account === LedgerAccount::VendorPayable->value) {
                $balances[$tenant]['payable'] = (int) $row->credit - (int) $row->debit;
            } else {
                $balances[$tenant]['receivable'] = (int) $row->debit - (int) $row->credit;
            }
        }

        return $balances;
    }

    public function listing(array $filters = [], ?int $tenantId = null): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return LedgerTransaction::withoutTenantScope()
            ->withCount('entries')
            ->when($tenantId !== null, static fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->when(filled($filters['tenant_id'] ?? null), static fn (Builder $q) => $q->where('tenant_id', (int) $filters['tenant_id']))
            ->when(filled($filters['direction'] ?? null), static fn (Builder $q) => $q->where('direction', (string) $filters['direction']))
            ->when(filled($filters['source'] ?? null), static fn (Builder $q) => $q->where('source', (string) $filters['source']))
            ->when(filled($filters['from'] ?? null), static fn (Builder $q) => $q->whereDate('occurred_at', '>=', (string) $filters['from']))
            ->when(filled($filters['to'] ?? null), static fn (Builder $q) => $q->whereDate('occurred_at', '<=', (string) $filters['to']))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('reference', 'like', '%'.$search.'%')
                ->orWhere('source_reference', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')
                ->when($tenantId === null, static fn (Builder $any) => $any->orWhereIn('tenant_id', Tenant::query()->where('name', 'like', '%'.$search.'%')->select('id')))));
    }

    public function findDetailed(int $id, ?int $tenantId = null): ?LedgerTransaction
    {
        return LedgerTransaction::withoutTenantScope()
            ->with(['entries', 'tenant'])
            ->whereKey($id)
            ->when($tenantId !== null, static fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->first();
    }
}
