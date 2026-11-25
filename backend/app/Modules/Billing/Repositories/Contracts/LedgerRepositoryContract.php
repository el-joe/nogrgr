<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Central ledger (decision #72). All reads are explicit per store (no tenant scope).
 *
 * @extends RepositoryContract<LedgerTransaction>
 */
interface LedgerRepositoryContract extends RepositoryContract
{
    public function findByKey(string $idempotencyKey): ?LedgerTransaction;

    /**
     * Locks the store row and returns its last running net (serialises postings per store).
     */
    public function lockedBalance(int $tenantId): int;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $entries
     */
    public function record(array $attributes, array $entries): LedgerTransaction;

    /**
     * Σ(debit − credit) of an account for a store (optionally one order, optionally only
     * some sources).
     *
     * @param  list<LedgerSource>|null  $sources
     */
    public function accountBalance(int $tenantId, LedgerAccount $account, ?string $orderUuid = null, ?array $sources = null): int;

    /**
     * Orders whose receivable is still open: uuid => [number, owed (minor), since].
     *
     * @return Collection<string, array{uuid: string, number: string|null, owed: int, since: string}>
     */
    public function openReceivables(int $tenantId): Collection;

    /**
     * Per-store balances: tenant id => [payable, receivable] (payable = credit − debit,
     * receivable = debit − credit).
     *
     * @param  list<int>|null  $tenantIds
     * @return array<int, array{payable: int, receivable: int}>
     */
    public function balances(?array $tenantIds = null): array;

    /**
     * Transactions list (ADM-053 / ADM-050a / vendor). Filters: search, direction,
     * source, tenant_id, from, to.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<LedgerTransaction>
     */
    public function listing(array $filters = [], ?int $tenantId = null): Builder;

    public function findDetailed(int $id, ?int $tenantId = null): ?LedgerTransaction;
}
