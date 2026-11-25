<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Models\TenantPayout;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform → store payouts (central, ADM-052 / VEN-064).
 *
 * @extends RepositoryContract<TenantPayout>
 */
interface TenantPayoutRepositoryContract extends RepositoryContract
{
    /**
     * Filters: search, status, method, tenant_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TenantPayout>
     */
    public function listing(array $filters = [], ?int $tenantId = null): Builder;

    public function findForTenant(int $id, ?int $tenantId = null): ?TenantPayout;

    public function lock(TenantPayout $payout): TenantPayout;

    /**
     * Σ open (scheduled + processing) payouts of a store, or of all stores.
     */
    public function openTotal(?int $tenantId = null): int;

    /**
     * @return array{scheduled: int, processing: int, paid: int, cancelled: int, open_amount: int}
     */
    public function stats(): array;
}
