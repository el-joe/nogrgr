<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\PlanData;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Plan CRUD. A plan with open (trial / active / past-due) subscriptions cannot be deleted.
 */
class PlanService extends BaseService
{
    public function __construct(private readonly PlanRepositoryContract $plans) {}

    public function create(PlanData $data): Plan
    {
        /** @var Plan $plan */
        $plan = $this->plans->create($data->attributes());

        return $plan;
    }

    public function update(Plan $plan, PlanData $data): Plan
    {
        /** @var Plan $plan */
        $plan = $this->plans->update($plan, $data->attributes());

        return $plan;
    }

    public function delete(Plan $plan): void
    {
        $open = $this->plans->openSubscriptionsCount($plan);

        if ($open > 0) {
            throw BillingRuleException::planInUse($open);
        }

        if ($plan->subscriptions()->withoutGlobalScopes()->exists()) {
            throw BillingRuleException::planHasHistory();
        }

        $this->plans->delete($plan);
    }
}
