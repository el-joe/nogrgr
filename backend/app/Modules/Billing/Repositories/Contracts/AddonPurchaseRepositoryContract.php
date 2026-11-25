<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central DB; queries are tenant scoped in tenant context (BelongsToCurrentTenant).
 *
 * @extends RepositoryContract<AddonPurchase>
 */
interface AddonPurchaseRepositoryContract extends RepositoryContract
{
    /**
     * The store's unpaid purchase of an item, if any.
     */
    public function openFor(int $tenantId, AddonType $type, string $itemCode): ?AddonPurchase;

    /**
     * @return Collection<int, AddonPurchase>
     */
    public function forTenant(int $tenantId): Collection;
}
