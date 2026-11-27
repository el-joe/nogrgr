<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Models\Subscription;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * Central DB; tenant scoped in tenant context.
 *
 * @extends RepositoryContract<Subscription>
 */
interface SubscriptionRepositoryContract extends RepositoryContract
{
    /**
     * List query (admin): plan + latest invoice eager loaded, filters applied.
     *
     * @param  array<string, mixed>  $filters  status, plan_id, gateway, search, owner_id, tenant_id
     * @return Builder<Subscription>
     */
    public function listing(array $filters = []): Builder;

    public function currentFor(int $tenantId): ?Subscription;

    /**
     * Open subscriptions whose trial or period ended before $now (expiry job input).
     *
     * @return LazyCollection<int, Subscription>
     */
    public function lapsedBefore(Carbon $now): LazyCollection;

    /**
     * Active / trial subscriptions whose current period (trial: trial end) ends inside
     * [$from, $to) (renewal reminder input), with their plan.
     *
     * @return LazyCollection<int, Subscription>
     */
    public function endingBetween(Carbon $from, Carbon $to): LazyCollection;

    /**
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array;

    /**
     * Active + past-due subscriptions as monthly amounts (yearly / 12), USD minor units.
     */
    public function monthlyRecurringMinor(): int;

    public function countCreatedSince(Carbon $since): int;

    public function countEndedSince(Carbon $since): int;

    public function countOpen(): int;

    /**
     * @return array{converted: int, ended: int} trials that ended, and how many of them became paying
     */
    public function trialOutcomes(): array;
}
