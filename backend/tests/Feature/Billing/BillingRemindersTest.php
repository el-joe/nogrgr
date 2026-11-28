<?php

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\VendorBillingMail;
use App\Modules\Billing\Services\Reminders\BillingReminderService;
use App\Modules\Identity\Models\Owner;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\SentReminder;
use App\Modules\Notifications\Notifications\OwnerInboxNotification;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/BillingHelpers.php';

/*
| Decision #124: subscription renewal reminders 7 / 3 / 1 days before the period ends
| (owner e-mail + owner-portal inbox, idempotent per period and day through
| sent_reminders) and owner-portal inbox copies of billing e-mails.
*/

function reminderTenant(): array
{
    $owner = Owner::factory()->create(['email' => 'owner@megz.test']);
    $tenant = billingTenant();
    $tenant->forceFill(['owner_id' => $owner->id, 'owner_name' => 'Mona Khaled', 'owner_email' => 'owner@megz.test'])->save();

    return [$tenant, $owner];
}

it('reminds the owner 7, 3 and 1 days before the renewal, once per day mark', function (int $days): void {
    Notification::fake();
    [$tenant, $owner] = reminderTenant();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->startOfDay()->addDays($days)->addHours(10)]);
    $reminders = app(BillingReminderService::class);

    expect($reminders->sendRenewalReminders())->toBe(1)
        ->and($reminders->sendRenewalReminders())->toBe(0);

    Notification::assertSentOnDemand(VendorBillingMail::class, fn (VendorBillingMail $mail): bool => $mail->template === EmailTemplateEvent::VendorSubscriptionRenewalReminder
        && $mail->variables['reminder.days'] === (string) $days);
    Notification::assertSentTo($owner, OwnerInboxNotification::class, fn (OwnerInboxNotification $notification, array $channels): bool => $notification->event() === 'subscription_renewal_reminder'
        && ! in_array('mail', $channels, true));
    expect(SentReminder::query()->where('reminder', 'renewal_reminder')->count())->toBe(1);
})->with([7, 3, 1]);

it('sends no reminder on other days or for cancelled plans', function (): void {
    Notification::fake();
    [$tenant] = reminderTenant();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->addDays(5)]);
    Subscription::factory()->status(SubscriptionStatus::Cancelled)->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->startOfDay()->addDays(3)->addHour()]);

    expect(app(BillingReminderService::class)->sendRenewalReminders())->toBe(0);
    Notification::assertNothingSent();
});

it('runs the scheduled reminder commands', function (): void {
    $this->artisan('billing:renewal-reminders')->assertSuccessful();
    $this->artisan('billing:settlement-reminders')->assertSuccessful();
});

it('copies billing e-mails into the owner-portal inbox', function (): void {
    Notification::fake();
    [$tenant, $owner] = reminderTenant();
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'bill_to_email' => 'owner@megz.test']);

    event(new InvoiceIssued($invoice));

    Notification::assertSentOnDemand(VendorBillingMail::class);
    Notification::assertSentTo($owner, OwnerInboxNotification::class, fn (OwnerInboxNotification $notification, array $channels): bool => $notification->event() === 'invoice_issued'
        && in_array('database', $channels, true)
        && ! in_array('mail', $channels, true));
});

it('does not copy into an inbox when the store has no owner account', function (): void {
    Notification::fake();
    $tenant = billingTenant();
    $tenant->forceFill(['owner_email' => 'nobody@megz.test'])->save();

    event(new InvoiceIssued(Invoice::factory()->create(['tenant_id' => $tenant->id])));

    Notification::assertSentTimes(OwnerInboxNotification::class, 0);
    expect(Tenant::query()->whereKey($tenant->id)->value('owner_id'))->toBeNull();
});
