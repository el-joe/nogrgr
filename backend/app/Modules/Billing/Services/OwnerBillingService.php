<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\OwnerService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;

/**
 * Read side of the owner portal billing: the owner's stores for the filters, the
 * past-due alert above the subscriptions list and, for the My stores cards, each
 * store's current subscription and oldest unpaid invoice.
 */
class OwnerBillingService extends BaseService
{
    public function __construct(
        private readonly OwnerService $owners,
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly InvoiceRepositoryContract $invoices,
    ) {}

    /**
     * @return array<int, string> tenant id => store name
     */
    public function storeOptions(Owner $owner): array
    {
        return $this->owners->stores($owner)
            ->mapWithKeys(static fn (Tenant $tenant): array => [$tenant->id => $tenant->name])
            ->all();
    }

    /**
     * The first past-due subscription of the owner's stores (with its unpaid invoice).
     */
    public function pastDue(Owner $owner): ?Subscription
    {
        /** @var Subscription|null $subscription */
        $subscription = $this->subscriptions
            ->listing(['owner_id' => $owner->id, 'status' => SubscriptionStatus::PastDue->value])
            ->orderBy('subscriptions.id')
            ->first();

        return $subscription;
    }

    /**
     * Current (open) subscription per store, newest first wins.
     *
     * @return array<int, Subscription> tenant id => subscription
     */
    public function currentSubscriptions(Owner $owner): array
    {
        $current = [];

        $this->subscriptions->listing(['owner_id' => $owner->id])
            ->whereIn('subscriptions.status', array_map(static fn (SubscriptionStatus $status): string => $status->value, SubscriptionStatus::open()))
            ->orderByDesc('subscriptions.id')
            ->get()
            ->each(function (Subscription $subscription) use (&$current): void {
                $current[$subscription->tenant_id] ??= $subscription;
            });

        return $current;
    }

    /**
     * Oldest unpaid invoice per store.
     *
     * @return array<int, Invoice> tenant id => invoice
     */
    public function unpaidInvoices(Owner $owner): array
    {
        $unpaid = [];

        $this->invoices->listing(['owner_id' => $owner->id, 'status' => InvoiceStatus::Unpaid->value])
            ->orderBy('invoices.id')
            ->get()
            ->each(function (Invoice $invoice) use (&$unpaid): void {
                $unpaid[$invoice->tenant_id] ??= $invoice;
            });

        return $unpaid;
    }
}
