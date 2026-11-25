<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Store change requests (selling countries / categories), central DB.
 *
 * @extends RepositoryContract<TenantChangeRequest>
 */
interface TenantChangeRequestRepositoryContract extends RepositoryContract
{
    /**
     * Filters: type, status, tenant_id, search (store name / slug).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TenantChangeRequest>
     */
    public function listing(array $filters = []): Builder;

    public function pendingCount(): int;

    public function pendingFor(int $tenantId, string $type): ?TenantChangeRequest;
}
