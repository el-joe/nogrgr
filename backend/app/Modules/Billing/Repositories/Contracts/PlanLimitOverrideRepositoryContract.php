<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Models\PlanLimitOverride;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Per-store plan limit overrides (central, tenant scoped).
 *
 * @extends RepositoryContract<PlanLimitOverride>
 */
interface PlanLimitOverrideRepositoryContract extends RepositoryContract
{
    /**
     * Overridden limits of a store: limit value => override (null = unlimited).
     * Limits without an override are absent.
     *
     * @return array<string, int|null>
     */
    public function valuesFor(int $tenantId): array;

    /**
     * Replace every override of the store with the given set (limit value => value,
     * null = unlimited). Limits missing from the set fall back to the plan.
     *
     * @param  array<string, int|null>  $values
     */
    public function replaceFor(int $tenantId, array $values): void;
}
