<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanLimitOverrideMode;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanLimitOverrideRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * The quota that applies to a store (null = unlimited): the admin override of the store
 * (ADM-041h) when one exists, otherwise the limit of the plan of the store's open
 * subscription. Without an override and without an open subscription nothing is capped
 * (unchanged behaviour). Every quota check reads its limit from here.
 */
class EffectivePlanLimits extends BaseService
{
    public function __construct(
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly PlanLimitOverrideRepositoryContract $overrides,
    ) {}

    public function for(PlanLimit $limit, int $tenantId): ?int
    {
        $overrides = $this->overrides->valuesFor($tenantId);

        if (array_key_exists($limit->value, $overrides)) {
            return $overrides[$limit->value];
        }

        return $this->subscriptions->currentFor($tenantId)?->plan?->limit($limit);
    }

    /**
     * Same as for() when the caller already holds the store's current plan.
     */
    public function forPlan(PlanLimit $limit, int $tenantId, ?Plan $plan): ?int
    {
        $overrides = $this->overrides->valuesFor($tenantId);

        return array_key_exists($limit->value, $overrides) ? $overrides[$limit->value] : $plan?->limit($limit);
    }

    /**
     * Admin view of every quota of the store: plan value, override mode / value and the
     * effective value. Plan values are null when unlimited or without a subscription
     * (has_plan tells the two apart).
     *
     * @return list<array{limit: PlanLimit, has_plan: bool, plan: int|null, mode: PlanLimitOverrideMode, override: int|null, effective: int|null}>
     */
    public function rows(int $tenantId): array
    {
        $plan = $this->subscriptions->currentFor($tenantId)?->plan;
        $overrides = $this->overrides->valuesFor($tenantId);
        $rows = [];

        foreach (PlanLimit::cases() as $limit) {
            $overridden = array_key_exists($limit->value, $overrides);
            $override = $overridden ? $overrides[$limit->value] : null;
            $planValue = $plan?->limit($limit);

            $rows[] = [
                'limit' => $limit,
                'has_plan' => $plan !== null,
                'plan' => $planValue,
                'mode' => match (true) {
                    ! $overridden => PlanLimitOverrideMode::Plan,
                    $override === null => PlanLimitOverrideMode::Unlimited,
                    default => PlanLimitOverrideMode::Custom,
                },
                'override' => $override,
                'effective' => $overridden ? $override : $planValue,
            ];
        }

        return $rows;
    }
}
