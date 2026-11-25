<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;

/**
 * KPI cards of the subscriptions page (Figma 73:58869): MRR, new (30d), churn (30d),
 * trials converting.
 */
class SubscriptionMetricsService extends BaseService
{
    public function __construct(private readonly SubscriptionRepositoryContract $subscriptions) {}

    /**
     * @return array{mrr: Money, new: int, churn_percent: float, trial_conversion_percent: float|null}
     */
    public function summary(): array
    {
        $since = now()->subDays(30);
        $ended = $this->subscriptions->countEndedSince($since);
        $base = $this->subscriptions->countOpen() + $ended;
        $trials = $this->subscriptions->trialOutcomes();

        return [
            'mrr' => Money::ofMinor($this->subscriptions->monthlyRecurringMinor()),
            'new' => $this->subscriptions->countCreatedSince($since),
            'churn_percent' => $base > 0 ? round($ended / $base * 100, 1) : 0.0,
            'trial_conversion_percent' => $trials['ended'] > 0 ? round($trials['converted'] / $trials['ended'] * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function countsByStatus(): array
    {
        return $this->subscriptions->countsByStatus();
    }
}
