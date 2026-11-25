<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\OwnerBillingService;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\OwnerService;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\OwnerStoreState;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Carbon;

/**
 * Owner portal › My stores (OWN-001) and Select store (OWN-002): one card per owned
 * store with its state (store lifecycle + billing), plan, renewal / trial date,
 * connected domain and unpaid invoice, plus the summary tiles.
 */
class OwnerPortalService extends BaseService
{
    /** A trial ending within this many days counts as "needs attention". */
    public const TRIAL_WARNING_DAYS = 7;

    public function __construct(
        private readonly OwnerService $owners,
        private readonly OwnerBillingService $billing,
        private readonly DomainRequestRepositoryContract $domains,
    ) {}

    /**
     * @return list<array{tenant: Tenant, state: OwnerStoreState, subscription: ?Subscription, invoice: ?Invoice, hosts: list<string>, trialDaysLeft: ?int}>
     */
    public function cards(Owner $owner): array
    {
        $stores = $this->owners->stores($owner);
        $subscriptions = $this->billing->currentSubscriptions($owner);
        $invoices = $this->billing->unpaidInvoices($owner);
        $connected = $this->domains->query()
            ->whereIn('tenant_id', $stores->modelKeys())
            ->where('status', DomainRequestStatus::Connected->value)
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id');

        $cards = [];

        foreach ($stores as $tenant) {
            $subscription = $subscriptions[$tenant->id] ?? null;
            $trialEnds = $subscription?->status === SubscriptionStatus::Trial ? $subscription->trial_ends_at : null;

            $cards[] = [
                'tenant' => $tenant,
                'state' => $this->state($tenant, $subscription),
                'subscription' => $subscription,
                'invoice' => $invoices[$tenant->id] ?? null,
                'hosts' => [$tenant->subdomainHost(), ...$connected->get($tenant->id, collect())->map(fn (DomainRequest $domain): string => $domain->host)->all()],
                'trialDaysLeft' => $trialEnds instanceof Carbon ? max(0, (int) ceil(now()->diffInDays($trialEnds, false))) : null,
            ];
        }

        return $cards;
    }

    /**
     * One of the owner's stores, or null.
     */
    public function ownedStore(Owner $owner, int $tenantId): ?Tenant
    {
        return $this->owners->stores($owner)->firstWhere('id', $tenantId);
    }

    /**
     * @param  list<array{tenant: Tenant, state: OwnerStoreState, subscription: ?Subscription, invoice: ?Invoice, hosts: list<string>, trialDaysLeft: ?int}>  $cards
     * @return array{stores: int, live: int, attention: int, nextRenewal: ?Carbon}
     */
    public function summary(array $cards): array
    {
        $attention = 0;
        $live = 0;
        $next = null;

        foreach ($cards as $card) {
            $live += $card['state'] === OwnerStoreState::Live ? 1 : 0;
            $needs = $card['state'] === OwnerStoreState::Trial
                ? ($card['trialDaysLeft'] ?? 0) <= self::TRIAL_WARNING_DAYS
                : $card['state']->needsAttention();
            $attention += $needs ? 1 : 0;
            $date = $card['subscription']?->nextBillingAt();

            if ($date !== null && $date->isFuture() && ($next === null || $date->lt($next))) {
                $next = $date;
            }
        }

        return ['stores' => count($cards), 'live' => $live, 'attention' => $attention, 'nextRenewal' => $next];
    }

    public function state(Tenant $tenant, ?Subscription $subscription): OwnerStoreState
    {
        return match (true) {
            in_array($tenant->status, [TenantStatus::Suspended, TenantStatus::Cancelled], true) => OwnerStoreState::Suspended,
            ! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true) => OwnerStoreState::NotLaunched,
            $subscription?->status === SubscriptionStatus::PastDue => OwnerStoreState::PastDue,
            $subscription?->status === SubscriptionStatus::Trial => OwnerStoreState::Trial,
            default => OwnerStoreState::Live,
        };
    }
}
