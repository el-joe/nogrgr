<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * @extends BaseRepository<Subscription>
 */
class SubscriptionRepository extends BaseRepository implements SubscriptionRepositoryContract
{
    protected function model(): string
    {
        return Subscription::class;
    }

    public function listing(array $filters = []): Builder
    {
        $query = Subscription::query()
            ->select('subscriptions.*')
            ->with(['plan', 'latestInvoice', 'tenant']);

        return $this->applyFilters($query, $filters);
    }

    public function currentFor(int $tenantId): ?Subscription
    {
        return Subscription::query()
            ->forTenant($tenantId)
            ->whereIn('status', $this->openValues())
            ->latest('id')
            ->first();
    }

    public function lapsedBefore(Carbon $now): LazyCollection
    {
        return Subscription::withoutTenantScope()
            ->whereIn('status', [...$this->openValues(), SubscriptionStatus::Cancelled->value])
            ->where(function (Builder $query) use ($now): void {
                $query->where(fn (Builder $trial) => $trial->where('status', SubscriptionStatus::Trial->value)->where('trial_ends_at', '<', $now))
                    ->orWhere(fn (Builder $period) => $period->where('status', '!=', SubscriptionStatus::Trial->value)->where('current_period_ends_at', '<', $now));
            })
            ->orderBy('id')
            ->lazyById();
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = Subscription::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return $counts;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $plan = $filters['plan_id'] ?? null;
        $gateway = $filters['gateway'] ?? null;
        $search = $filters['search'] ?? null;
        $ownerId = filter_var($filters['owner_id'] ?? null, FILTER_VALIDATE_INT);
        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);

        return $query
            // Owner portal: the owner's stores only (set by the server, never by the request).
            ->when($ownerId !== false && $ownerId !== null, fn (Builder $q) => $q->whereIn('subscriptions.tenant_id', Tenant::query()->select('id')->where('owner_id', $ownerId)))
            ->when($tenantId !== false && $tenantId !== null, fn (Builder $q) => $q->where('subscriptions.tenant_id', $tenantId))
            ->when(is_string($status) && SubscriptionStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('subscriptions.status', $status))
            ->when(is_numeric($plan), fn (Builder $q) => $q->where('subscriptions.plan_id', (int) $plan))
            ->when(is_string($gateway) && $gateway !== '', fn (Builder $q) => $q->where('subscriptions.gateway', $gateway))
            ->when(is_string($search) && $search !== '', function (Builder $q) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->whereIn('subscriptions.tenant_id', Tenant::query()->select('id')->where('name', 'like', $term)->orWhere('slug', 'like', $term))
                        ->orWhereHas('invoices', fn (Builder $invoice) => $invoice->where('number', 'like', $term));
                });
            });
    }

    public function monthlyRecurringMinor(): int
    {
        $rows = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->selectRaw('term, sum(amount_minor) as total')
            ->groupBy('term')
            ->pluck('total', 'term');

        $monthly = 0;

        foreach ($rows as $term => $total) {
            $months = BillingTerm::tryFrom((string) $term)?->months() ?? 1;
            $monthly += intdiv((int) $total, $months);
        }

        return $monthly;
    }

    public function countCreatedSince(Carbon $since): int
    {
        return Subscription::query()->where('created_at', '>=', $since)->count();
    }

    public function countEndedSince(Carbon $since): int
    {
        return Subscription::query()
            ->where(fn (Builder $query) => $query->where('cancelled_at', '>=', $since)
                ->orWhere(fn (Builder $expired) => $expired->whereNull('cancelled_at')->where('expired_at', '>=', $since)))
            ->count();
    }

    public function countOpen(): int
    {
        return Subscription::query()->whereIn('status', $this->openValues())->count();
    }

    public function trialOutcomes(): array
    {
        $ended = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->where('status', '!=', SubscriptionStatus::Trial->value);

        return [
            'ended' => (clone $ended)->count(),
            'converted' => (clone $ended)->whereNotNull('current_period_starts_at')->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function openValues(): array
    {
        return array_map(fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::open());
    }
}
