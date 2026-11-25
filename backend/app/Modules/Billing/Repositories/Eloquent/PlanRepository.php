<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Plan>
 */
class PlanRepository extends BaseRepository implements PlanRepositoryContract
{
    protected function model(): string
    {
        return Plan::class;
    }

    public function query(): Builder
    {
        return Plan::query()->withCount(['subscriptions as open_subscriptions_count' => function (Builder $query): void {
            $query->withoutGlobalScopes()->whereIn('status', array_map(fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::open()));
        }]);
    }

    public function ordered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function openSubscriptionsCount(Plan $plan): int
    {
        return $plan->subscriptions()
            ->withoutGlobalScopes()
            ->whereIn('status', array_map(fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::open()))
            ->count();
    }

    public function options(bool $activeOnly = false): array
    {
        return Plan::query()
            ->when($activeOnly, fn (Builder $query) => $query->where('status', PlanStatus::Active->value))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])
            ->all();
    }
}
