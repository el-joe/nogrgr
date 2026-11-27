<?php

namespace App\Modules\Billing\Services\Reminders;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\VendorBillingMail;
use App\Modules\Billing\Repositories\Contracts\LedgerRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Billing\Services\Finance\StoreFinanceNotifier;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\OwnerInboxNotification;
use App\Modules\Notifications\Repositories\Contracts\SentReminderRepositoryContract;
use App\Modules\Notifications\Services\OwnerInboxService;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Scheduled billing reminders (decision #124), idempotent per period through the
 * central `sent_reminders` log (a re-run or a second server sends nothing twice):
 *   renewal  — owner e-mail `vendor.subscription_renewal_reminder` + owner-portal inbox
 *              7, 3 and 1 days before an active (or trial) subscription's period ends;
 *              period key = "<end date>:<days>d", so a renewed period reminds again;
 *   settlement due — weekly (ISO week) to the store owners when the ledger says the
 *              store owes the platform (receivable > payable): inbox + Reverb + mail
 *              `vendor.settlement_due` (VEN-087 topic "settlement_due").
 */
class BillingReminderService extends BaseService
{
    /**
     * @var list<int>
     */
    public const RENEWAL_DAYS = [7, 3, 1];

    public function __construct(
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly LedgerRepositoryContract $ledger,
        private readonly TenantRepositoryContract $tenants,
        private readonly SentReminderRepositoryContract $sent,
        private readonly OwnerInboxService $ownerInbox,
        private readonly StoreFinanceNotifier $finance,
    ) {}

    /**
     * @return int reminders sent
     */
    public function sendRenewalReminders(?Carbon $now = null): int
    {
        $today = ($now ?? now())->copy()->startOfDay();
        $sent = 0;

        foreach (self::RENEWAL_DAYS as $days) {
            $from = $today->copy()->addDays($days);

            foreach ($this->subscriptions->endingBetween($from, $from->copy()->addDay()) as $subscription) {
                if ($this->renewalReminder($subscription, $days)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * @return int stores reminded
     */
    public function sendSettlementDueReminders(?Carbon $now = null): int
    {
        $week = ($now ?? now())->format('o-\WW');
        $sent = 0;

        foreach ($this->ledger->balances() as $tenantId => $balance) {
            $owed = (int) $balance['receivable'] - (int) $balance['payable'];

            if ($owed <= 0) {
                continue;
            }

            $tenant = $this->tenants->find((int) $tenantId);

            if (! $tenant instanceof Tenant || $tenant->status !== TenantStatus::Active || ! $this->sent->claim('settlement_due', 'tenant:'.$tenantId, $week)) {
                continue;
            }

            $this->finance->notify((int) $tenantId, 'settlement_due', ['amount' => Money::ofMinor($owed)->format()], 'finance.settlements.index');
            $sent++;
        }

        return $sent;
    }

    private function renewalReminder(Subscription $subscription, int $days): bool
    {
        $endsAt = $subscription->status === SubscriptionStatus::Trial ? $subscription->trial_ends_at : $subscription->current_period_ends_at;
        $tenant = $this->tenants->find($subscription->tenant_id);

        if ($endsAt === null || ! $tenant instanceof Tenant || ! $this->sent->claim('renewal_reminder', 'subscription:'.$subscription->id, $endsAt->toDateString().':'.$days.'d')) {
            return false;
        }

        $plan = (string) $subscription->plan?->name;
        $date = $endsAt->toDateString();
        $renewUrl = rescue(static fn (): string => route('owner.subscriptions.index'), route('central.tenant.login'), false);

        try {
            if (filled($tenant->owner_email)) {
                Notification::route('mail', (string) $tenant->owner_email)->notify(new VendorBillingMail(
                    EmailTemplateEvent::VendorSubscriptionRenewalReminder,
                    'subscription_renewal_reminder',
                    [
                        'vendor.name' => (string) ($tenant->owner_name ?? ''),
                        'store.name' => (string) $tenant->name,
                        'plan.name' => $plan,
                        'subscription.ends_at' => $date,
                        'reminder.days' => (string) $days,
                        'renew.url' => $renewUrl,
                    ],
                    (string) config('app.locale', 'en'),
                    $renewUrl,
                ));
            }

            $this->ownerInbox->send($tenant, new OwnerInboxNotification(
                NotificationCategory::Vendors,
                'subscription_renewal_reminder',
                'billing::notifications.owner.renewal_reminder.title',
                'billing::notifications.owner.renewal_reminder.body',
                ['days' => $days, 'plan' => $plan, 'store' => (string) $tenant->name, 'date' => $date],
                $renewUrl,
            ));
        } catch (Throwable $exception) {
            // Let the next run try again.
            $this->sent->release('renewal_reminder', 'subscription:'.$subscription->id, $date.':'.$days.'d');

            throw $exception;
        }

        return true;
    }
}
