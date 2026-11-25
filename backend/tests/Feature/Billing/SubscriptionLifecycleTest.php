<?php

use App\Modules\Billing\DTOs\InvoiceDraftData;
use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Events\SubscriptionCancelled;
use App\Modules\Billing\Events\SubscriptionExpired;
use App\Modules\Billing\Events\SubscriptionPastDue;
use App\Modules\Billing\Events\SubscriptionRenewed;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Jobs\ExpireSubscriptions;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/BillingHelpers.php';

/*
| SubscriptionService state machine: subscribe (trial / active), renew, activate on
| payment, cancel, expire, and the scheduled expiry job.
*/

it('starts a trial without an invoice when the plan has trial days', function (): void {
    Event::fake([SubscriptionActivated::class]);
    $tenant = billingTenant();
    $plan = Plan::factory()->withTrial(14)->create();

    $subscription = app(SubscriptionService::class)->subscribe($tenant->id, $plan, BillingTerm::Monthly);

    expect($subscription->status)->toBe(SubscriptionStatus::Trial)
        ->and($subscription->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($subscription->invoices()->count())->toBe(0);
    Event::assertNotDispatched(SubscriptionActivated::class);
});

it('activates immediately with an unpaid invoice when there is no trial', function (): void {
    Event::fake([SubscriptionActivated::class]);
    $tenant = billingTenant();
    $plan = Plan::factory()->create();

    $subscription = app(SubscriptionService::class)->subscribe($tenant->id, $plan, BillingTerm::Yearly);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->amount_minor)->toBe(58800)
        ->and($subscription->current_period_ends_at->isSameDay(now()->addYear()))->toBeTrue()
        ->and($subscription->invoices()->sole()->status)->toBe(InvoiceStatus::Unpaid);
    Event::assertDispatched(SubscriptionActivated::class);
});

it('activates a trial when its invoice is paid', function (): void {
    $tenant = billingTenant();
    $subscription = Subscription::factory()->trial()->create(['tenant_id' => $tenant->id]);
    $service = app(SubscriptionService::class);

    $service->renew($subscription);
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);

    $pastDue = Subscription::factory()->status(SubscriptionStatus::PastDue)->create([
        'tenant_id' => $tenant->id,
        'current_period_ends_at' => now()->subDays(2),
    ]);
    $invoice = app(InvoiceService::class)->issue(new InvoiceDraftData(
        tenantId: $tenant->id,
        type: InvoiceType::Renewal,
        lines: [new InvoiceLineData('Renewal', 5900)],
        billable: $pastDue,
    ));

    app(InvoiceService::class)->markPaid($invoice, 'stripe');

    $pastDue->refresh();
    expect($pastDue->status)->toBe(SubscriptionStatus::Active)
        ->and($pastDue->current_period_ends_at->isFuture())->toBeTrue();
});

it('renews from the end of the current period and fires events', function (): void {
    Event::fake([SubscriptionRenewed::class, SubscriptionActivated::class]);
    $subscription = Subscription::factory()->create(['tenant_id' => billingTenant()->id, 'current_period_ends_at' => now()->addDays(10)]);
    $end = $subscription->current_period_ends_at->copy();

    app(SubscriptionService::class)->renew($subscription, markPaid: true);

    expect($subscription->current_period_starts_at->equalTo($end))->toBeTrue()
        ->and($subscription->renewals_count)->toBe(1)
        ->and($subscription->invoices()->sole()->status)->toBe(InvoiceStatus::Paid);
    Event::assertDispatched(SubscriptionRenewed::class);
    Event::assertNotDispatched(SubscriptionActivated::class);
});

it('cancels and expires with events', function (): void {
    Event::fake([SubscriptionCancelled::class, SubscriptionExpired::class]);
    $subscription = Subscription::factory()->create(['tenant_id' => billingTenant()->id]);
    $service = app(SubscriptionService::class);

    $service->cancel($subscription);
    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->auto_renew)->toBeFalse()
        ->and($subscription->cancelled_at)->not->toBeNull();

    $service->expire($subscription);
    expect($subscription->status)->toBe(SubscriptionStatus::Expired);

    Event::assertDispatched(SubscriptionCancelled::class);
    Event::assertDispatched(SubscriptionExpired::class);
});

it('refuses invalid transitions', function (SubscriptionStatus $from, string $action): void {
    $subscription = Subscription::factory()->status($from)->create(['tenant_id' => billingTenant()->id]);

    expect(fn () => app(SubscriptionService::class)->{$action}($subscription))->toThrow(BillingRuleException::class);
})->with([
    'cancel expired' => [SubscriptionStatus::Expired, 'cancel'],
    'renew expired' => [SubscriptionStatus::Expired, 'renew'],
    'renew cancelled' => [SubscriptionStatus::Cancelled, 'renew'],
    'past-due a trial' => [SubscriptionStatus::Trial, 'markPastDue'],
    'expire expired' => [SubscriptionStatus::Expired, 'expire'],
]);

it('moves lapsed subscriptions to past due and expired on schedule', function (): void {
    Event::fake([SubscriptionPastDue::class, SubscriptionExpired::class]);
    config(['billing.grace_days' => 7]);
    $tenant = billingTenant();

    $lapsedActive = Subscription::factory()->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->subDay()]);
    $nonRenewing = Subscription::factory()->create(['tenant_id' => $tenant->id, 'auto_renew' => false, 'current_period_ends_at' => now()->subDay()]);
    $graceOver = Subscription::factory()->status(SubscriptionStatus::PastDue)->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->subDays(8)]);
    $inGrace = Subscription::factory()->status(SubscriptionStatus::PastDue)->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->subDays(3)]);
    $trialOver = Subscription::factory()->trial(-1)->create(['tenant_id' => $tenant->id]);
    $cancelledOver = Subscription::factory()->status(SubscriptionStatus::Cancelled)->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->subHour()]);
    $current = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    ExpireSubscriptions::dispatchSync();

    expect($lapsedActive->refresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($nonRenewing->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($graceOver->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($inGrace->refresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($trialOver->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($cancelledOver->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($current->refresh()->status)->toBe(SubscriptionStatus::Active);

    Event::assertDispatchedTimes(SubscriptionPastDue::class, 1);
    Event::assertDispatchedTimes(SubscriptionExpired::class, 4);
});
