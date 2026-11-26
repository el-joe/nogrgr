<?php

namespace App\Modules\Support\Repositories\Contracts;

use App\Modules\Support\Models\SupportTicket;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<SupportTicket>
 */
interface SupportTicketRepositoryContract extends RepositoryContract
{
    /**
     * List query with filters (search, tab, status, priority, category, tenant_id, assignee).
     * $ownerTenantIds limits the rows to one owner (their account tickets + their stores).
     *
     * @param  array<string, mixed>  $filters
     * @param  array{owner_id: int, tenant_ids: list<int>}|null  $owner
     * @return Builder<SupportTicket>
     */
    public function listing(array $filters, ?array $owner = null): Builder;

    /**
     * Ticket count per admin tab (open / waiting / resolved / closed / all).
     *
     * @return array<string, int>
     */
    public function tabCounts(): array;

    /**
     * @param  array{owner_id: int, tenant_ids: list<int>}|null  $owner
     */
    public function findVisible(int $id, ?array $owner = null): ?SupportTicket;

    public function latestVisible(): ?SupportTicket;

    public function countOpenForAdmins(): int;

    public function countUnreadForRequester(): int;
}
