<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Product edit requests (central DB); approved changes land in the tenant override
 * tables (ProductOverrideRepositoryContract, decision #65).
 *
 * @extends RepositoryContract<EditRequest>
 */
interface EditRequestRepositoryContract extends RepositoryContract
{
    /**
     * Filters: status, tenant_id, search (product name / sku, store, ER number).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<EditRequest>
     */
    public function listing(array $filters = []): Builder;

    public function pendingCount(): int;

    public function pendingFor(int $tenantId, int $productId, ?int $variantId): ?EditRequest;
}
