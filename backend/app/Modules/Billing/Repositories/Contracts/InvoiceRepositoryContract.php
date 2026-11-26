<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Models\Invoice;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * @extends RepositoryContract<Invoice>
 */
interface InvoiceRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters  status, type, search, owner_id, tenant_id
     * @return Builder<Invoice>
     */
    public function listing(array $filters = []): Builder;

    public function findWithItems(int $id): Invoice;

    /**
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array;

    /**
     * Paid store invoices (all stores, or the given ones), oldest first — the ledger back-fill source.
     *
     * @param  list<int>|null  $tenantIds
     * @return LazyCollection<int, Invoice>
     */
    public function paidCursor(?array $tenantIds = null): LazyCollection;
}
