<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Models\Plan;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Plan>
 */
interface PlanRepositoryContract extends RepositoryContract
{
    /**
     * Plans in display order with their open (trial/active/past_due) subscription counts.
     *
     * @return Collection<int, Plan>
     */
    public function ordered(): Collection;

    public function openSubscriptionsCount(Plan $plan): int;

    /**
     * id => name, for selects.
     *
     * @return array<int, string>
     */
    public function options(bool $activeOnly = false): array;
}
