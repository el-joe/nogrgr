<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\AddonPurchaseService;
use App\Modules\Billing\Services\SubscriptionService;

/**
 * A paid invoice settles what it charged for: a trial / past-due subscription becomes
 * active, a pending add-on purchase becomes paid.
 */
final class SettleInvoiceBillable
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AddonPurchaseService $addons,
    ) {}

    public function handle(InvoicePaid $event): void
    {
        if ($event->invoice->billable_type === null || $event->invoice->billable_id === null) {
            return;
        }

        $billable = $event->invoice->billable()->withoutGlobalScopes()->first();

        if ($billable instanceof Subscription && in_array($billable->status, [SubscriptionStatus::Trial, SubscriptionStatus::PastDue], true)) {
            $this->subscriptions->activate($billable);
        }

        if ($billable instanceof AddonPurchase && $billable->status === AddonPurchaseStatus::PendingPayment) {
            $this->addons->markPaid($billable);
        }
    }
}
