<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * @extends RepositoryContract<Tenant>
 */
interface TenantRepositoryContract extends RepositoryContract
{
    public function findBySlug(string $slug): ?Tenant;

    /**
     * @param  list<int>  $ids
     */
    public function findMany(array $ids): TenantCollection;

    public function countByStatus(TenantStatus $status): int;
}
