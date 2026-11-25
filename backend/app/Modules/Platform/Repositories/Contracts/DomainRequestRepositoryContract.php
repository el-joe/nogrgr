<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\DomainRequest;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central DB.
 *
 * @extends RepositoryContract<DomainRequest>
 */
interface DomainRequestRepositoryContract extends RepositoryContract
{
    /**
     * Filters: status, tenant_id, owner_id (owner portal), search (host, store name / slug).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<DomainRequest>
     */
    public function listing(array $filters = []): Builder;

    /**
     * @return Collection<int, DomainRequest>
     */
    public function forTenant(int $tenantId): Collection;

    /**
     * A pending or connected request (any store) already uses this host.
     */
    public function hostInUse(string $host, ?int $exceptId = null): bool;

    /**
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array;
}
