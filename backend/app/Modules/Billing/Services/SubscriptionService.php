<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\AssignPlanData;
use App\Modules\Billing\DTOs\InvoiceDraftData;
use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Events\SubscriptionCancelled;
use App\Modules\Billing\Events\SubscriptionExpired;
use App\Modules\Billing\Events\SubscriptionPastDue;
use App\Modules\Billing\Events\SubscriptionRenewed;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Carbon;

/**
 * Subscription lifecycle (central connection):
 *   subscribe → trial (plan has trial days) | active (+ invoice)
 *   renew     → +1 term, active (+ renewal invoice)
 *   cancel / expire / markPastDue, and processLapsed() for the scheduled expiry job.
 * Every change is checked against SubscriptionStatus::canTransitionTo().
 */
class SubscriptionService extends BaseService
{
    public function __construct(
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly PlanRepositoryContract $plans,
        private readonly InvoiceService $invoices,
    ) {}

    public function subscribe(
        int $tenantId,
        Plan $plan,
        BillingTerm $term,
        ?PaymentGateway $gateway = null,
        bool $withTrial = true,
        ?int $adminId = null,
    ): Subscription {
        return $this->transaction(function () use ($tenantId, $plan, $term, $gateway, $withTrial, $adminId): Subscription {
            $current = $this->subscriptions->currentFor($tenantId);

            if ($current !== null) {
                $this->expire($current);
            }

            $now = now();
            $trial = $withTrial && $plan->trial_days > 0;

            /** @var Subscription $subscription */
            $subscription = $this->subscriptions->create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'term' => $term,
                'status' => $trial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
                'amount_minor' => $plan->priceFor($term)->minor,
                'currency' => $plan->currency,
                'gateway' => $gateway,
                'trial_ends_at' => $trial ? $now->copy()->addDays($plan->trial_days) : null,
                'current_period_starts_at' => $trial ? null : $now,
                'current_period_ends_at' => $trial ? null : $now->copy()->addMonthsNoOverflow($term->months()),
                'auto_renew' => true,
                'assigned_by_admin_id' => $adminId,
            ]);

            $subscription->setRelation('plan', $plan);

            if (! $trial) {
                $this->issueInvoice($subscription, InvoiceType::Subscription);
                $this->dispatchAfterCommit(new SubscriptionActivated($subscription));
            }

            return $subscription;
        }, $this->connection());
    }

    /**
     * Admin "assign plan manually": starts an active subscription now (no trial) and
     * issues its invoice, settled as a manual payment unless markPaid is false.
     */
    public function assign(AssignPlanData $data): Subscription
    {
        /** @var Plan $plan */
        $plan = $this->plans->findOrFail($data->planId);

        return $this->transaction(function () use ($data, $plan): Subscription {
            $subscription = $this->subscribe($data->tenantId, $plan, $data->term, PaymentGateway::Manual, false, $data->adminId);
            $invoice = $subscription->latestInvoice()->first();

            if ($data->markPaid && $invoice instanceof Invoice) {
                $this->invoices->markPaid($invoice, PaymentGateway::Manual->value);
            }

            return $subscription;
        }, $this->connection());
    }

    public function renew(Subscription $subscription, bool $markPaid = false): Subscription
    {
        $this->guard($subscription, SubscriptionStatus::Active);
        $wasActive = $subscription->status === SubscriptionStatus::Active;

        return $this->transaction(function () use ($subscription, $markPaid, $wasActive): Subscription {
            $now = now();
            $end = $subscription->current_period_ends_at;
            $start = $wasActive && $end !== null && $end->isFuture() ? $end->copy() : $now;

            $this->subscriptions->update($subscription, [
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $start,
                'current_period_ends_at' => $start->copy()->addMonthsNoOverflow($subscription->term->months()),
                'renewals_count' => $subscription->renewals_count + 1,
                'auto_renew' => true,
            ]);

            $invoice = $this->issueInvoice($subscription, InvoiceType::Renewal);

            if ($markPaid) {
                $this->invoices->markPaid($invoice, $subscription->gateway?->value);
            }

            $this->dispatchAfterCommit(new SubscriptionRenewed($subscription));

            if (! $wasActive) {
                $this->dispatchAfterCommit(new SubscriptionActivated($subscription));
            }

            return $subscription;
        }, $this->connection());
    }

    /**
     * Trial / past-due → active once its invoice is paid (InvoicePaid listener).
     */
    public function activate(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Active) {
            return $subscription;
        }

        $this->guard($subscription, SubscriptionStatus::Active);
        $now = now();
        $attributes = ['status' => SubscriptionStatus::Active];

        if ($subscription->status === SubscriptionStatus::Trial || $subscription->current_period_ends_at === null || $subscription->current_period_ends_at->isPast()) {
            $attributes['current_period_starts_at'] = $now;
            $attributes['current_period_ends_at'] = $now->copy()->addMonthsNoOverflow($subscription->term->months());
        }

        $this->subscriptions->update($subscription, $attributes);
        event(new SubscriptionActivated($subscription));

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $this->guard($subscription, SubscriptionStatus::Cancelled);

        $this->subscriptions->update($subscription, [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);
        event(new SubscriptionCancelled($subscription));

        return $subscription;
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        $this->guard($subscription, SubscriptionStatus::PastDue);

        $this->subscriptions->update($subscription, ['status' => SubscriptionStatus::PastDue]);
        event(new SubscriptionPastDue($subscription));

        return $subscription;
    }

    public function expire(Subscription $subscription): Subscription
    {
        $this->guard($subscription, SubscriptionStatus::Expired);

        $this->subscriptions->update($subscription, [
            'status' => SubscriptionStatus::Expired,
            'expired_at' => now(),
            'auto_renew' => false,
        ]);
        event(new SubscriptionExpired($subscription));

        return $subscription;
    }

    /**
     * Scheduled expiry (ExpireSubscriptions job):
     *  - trial ended                → expired
     *  - active period ended        → past_due (auto-renew) or expired
     *  - past_due beyond grace days → expired
     *  - cancelled period ended     → expired
     *
     * @return array{past_due: int, expired: int}
     */
    public function processLapsed(?Carbon $now = null): array
    {
        $now ??= now();
        $graceDays = (int) config('billing.grace_days', 7);
        $result = ['past_due' => 0, 'expired' => 0];

        foreach ($this->subscriptions->lapsedBefore($now) as $subscription) {
            $periodEnd = $subscription->current_period_ends_at;

            $next = match ($subscription->status) {
                SubscriptionStatus::Trial, SubscriptionStatus::Cancelled => SubscriptionStatus::Expired,
                SubscriptionStatus::Active => $subscription->auto_renew ? SubscriptionStatus::PastDue : SubscriptionStatus::Expired,
                SubscriptionStatus::PastDue => $periodEnd !== null && $periodEnd->copy()->addDays($graceDays)->lt($now) ? SubscriptionStatus::Expired : null,
                SubscriptionStatus::Expired => null,
            };

            if ($next === SubscriptionStatus::PastDue) {
                $this->markPastDue($subscription);
                $result['past_due']++;
            } elseif ($next === SubscriptionStatus::Expired) {
                $this->expire($subscription);
                $result['expired']++;
            }
        }

        return $result;
    }

    private function issueInvoice(Subscription $subscription, InvoiceType $type): Invoice
    {
        $plan = $subscription->plan;

        return $this->invoices->issue(new InvoiceDraftData(
            tenantId: $subscription->tenant_id,
            type: $type,
            lines: [new InvoiceLineData(
                description: __('billing::invoices.line.plan', ['plan' => $plan->name, 'term' => $subscription->term->label()]),
                unitAmountMinor: $subscription->amount_minor,
            )],
            billable: $subscription,
            billToName: $subscription->tenant?->getAttribute('name'),
        ));
    }

    private function guard(Subscription $subscription, SubscriptionStatus $next): void
    {
        if (! $subscription->status->canTransitionTo($next)) {
            throw BillingRuleException::invalidTransition($subscription->status->label(), $next->label());
        }
    }

    private function connection(): ?string
    {
        return (new Subscription)->getConnectionName();
    }
}
