<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * @extends BaseRepository<Tenant>
 */
class TenantRepository extends BaseRepository implements TenantRepositoryContract
{
    protected function model(): string
    {
        return Tenant::class;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return $this->query()->where('slug', strtolower($slug))->first();
    }

    /**
     * @param  list<int>  $ids
     */
    public function findMany(array $ids): TenantCollection
    {
        if ($ids === []) {
            return new TenantCollection;
        }

        return $this->query()->whereKey($ids)->orderBy('name')->get();
    }

    public function countByStatus(TenantStatus $status): int
    {
        return $this->query()->where('status', $status->value)->count();
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($count): int => (int) $count)->all();

        return $counts;
    }

    public function listing(array $filters = []): Builder
    {
        $open = array_map(static fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::open());

        $query = $this->query()
            ->select('tenants.*', 'current_subscription.plan_id as plan_id', 'current_subscription.status as subscription_status', 'current_subscription.trial_ends_at as trial_ends_at', 'plans.name as plan_name')
            ->leftJoin('subscriptions as current_subscription', function (JoinClause $join) use ($open): void {
                $join->on('current_subscription.tenant_id', '=', 'tenants.id')
                    ->whereIn('current_subscription.status', $open)
                    ->whereRaw('current_subscription.id = (select max(latest.id) from subscriptions as latest where latest.tenant_id = tenants.id)');
            })
            ->leftJoin('plans', 'plans.id', '=', 'current_subscription.plan_id');

        $status = TenantStatus::tryFrom((string) ($filters['status'] ?? ''));
        $subscriptionStatus = SubscriptionStatus::tryFrom((string) ($filters['subscription_status'] ?? ''));
        $planId = filter_var($filters['plan_id'] ?? null, FILTER_VALIDATE_INT);
        $countryId = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('tenants.status', $status?->value))
            ->when($subscriptionStatus !== null, fn (Builder $query) => $query->where('current_subscription.status', $subscriptionStatus?->value))
            ->when($planId !== false && $planId !== null, fn (Builder $query) => $query->where('current_subscription.plan_id', $planId))
            ->when($countryId !== false && $countryId !== null, fn (Builder $query) => $query->where('tenants.default_country_id', $countryId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('tenants.name', 'like', $term)
                    ->orWhere('tenants.slug', 'like', $term)
                    ->orWhere('tenants.owner_name', 'like', $term)
                    ->orWhere('tenants.owner_email', 'like', $term)
                    ->orWhereExists(fn (QueryBuilder $exists) => $exists->from('domains')
                        ->whereColumn('domains.tenant_id', 'tenants.id')
                        ->where('domains.domain', 'like', $term)));
            });
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->query()
            ->where('slug', strtolower($slug))
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }
}
