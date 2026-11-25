<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * @extends RepositoryContract<Tenant>
 */
interface TenantRepositoryContract extends RepositoryContract
{
    public function findBySlug(string $slug): ?Tenant;

    /**
     * @param  list<int>  $ids
     * @return TenantCollection<int, Tenant>
     */
    public function findMany(array $ids): TenantCollection;

    public function countByStatus(TenantStatus $status): int;

    /**
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array;

    /**
     * Admin vendors list: current subscription plan joined (plan_id / plan_name columns),
     * filters: status, subscription_status (current open subscription), plan_id, country_id, search (name, slug, owner name / e-mail, domain).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Tenant>
     */
    public function listing(array $filters = []): Builder;

    public function slugExists(string $slug, ?int $exceptId = null): bool;
}
