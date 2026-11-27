<?php

use App\Modules\Affiliates\Notifications\AffiliateAdminNotification;
use App\Modules\Billing\Notifications\BillingAlertNotification;
use App\Modules\Billing\Notifications\FinanceAlertNotification;
use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Identity\Models\Admin;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Platform\Events\DomainDnsFailed;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Notifications\DomainAlertNotification;
use App\Modules\Platform\Notifications\DomainDnsFailedNotification;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__).'/Billing/BillingHelpers.php';

/*
| Decision #124 open items: specific order shipping / return keys, the new alert keys
| (settlement, gateway limits, shipping escalation, affiliate payout request), the
| DNS-failed event and the plain-text part of every templated mail.
*/

it('uses order.shipping_update and order.return_update instead of order.update', function (string $event, EmailTemplateEvent $expected): void {
    $notification = new OrderCustomerNotification(null, $event, [], 'uuid');

    expect($notification->mailTemplate(new stdClass))->toBe($expected)
        ->and(OrderCustomerNotification::mailTemplates())->toContain($expected);
})->with([
    'out for delivery' => ['shipping_out_for_delivery', EmailTemplateEvent::OrderShippingUpdate],
    'delayed' => ['shipping_delayed', EmailTemplateEvent::OrderShippingUpdate],
    'return approved' => ['return_approved', EmailTemplateEvent::OrderReturnUpdate],
    'return message' => ['return_message', EmailTemplateEvent::OrderReturnUpdate],
    'anything else' => ['custom', EmailTemplateEvent::OrderUpdate],
]);

it('maps the new events to their own templates', function (string $class, string $event, EmailTemplateEvent $expected): void {
    expect($class::specificMailTemplates()[$event] ?? null)->toBe($expected)
        ->and($class::mailTemplates())->toContain($expected);
})->with([
    'settlement receipt' => [FinanceAlertNotification::class, 'settlement_paid', EmailTemplateEvent::VendorSettlementReceipt],
    'manual settlement' => [FinanceAlertNotification::class, 'settlement_confirmed', EmailTemplateEvent::VendorSettlementReceipt],
    'settlement due' => [FinanceAlertNotification::class, 'settlement_due', EmailTemplateEvent::VendorSettlementDue],
    'store gateway warning' => [FinanceAlertNotification::class, 'gateway_limit_warning', EmailTemplateEvent::VendorGatewayLimit],
    'admin gateway warning' => [BillingAlertNotification::class, 'gateway_limit_warning', EmailTemplateEvent::AdminGatewayLimitWarning],
    'admin gateway block' => [BillingAlertNotification::class, 'gateway_limit_block', EmailTemplateEvent::AdminGatewayLimitBlock],
    'shipping escalation' => [OrderAlertNotification::class, 'admin_shipping_escalation', EmailTemplateEvent::AdminShippingEscalation],
    'affiliate payout request' => [AffiliateAdminNotification::class, 'payout_requested', EmailTemplateEvent::AdminAffiliatePayoutRequest],
]);

it('sends every templated mail with a plain-text alternative', function (): void {
    $admin = Admin::factory()->create(['name' => 'Laila']);
    $mail = (new AffiliateAdminNotification('payout_requested', ['reference' => 'PR-1', 'name' => 'Sara', 'amount' => '$50.00'], 'https://nogrgr.test/admin/affiliates/payouts'))->toMail($admin);

    expect($mail->view)->toBe(['html' => 'notifications::mail.rendered', 'text' => 'notifications::mail.rendered-text'])
        ->and($mail->viewData['text'])->toContain('Sara')
        ->and($mail->viewData['text'])->toContain('(https://nogrgr.test/admin/affiliates/payouts)')
        ->and($mail->viewData['text'])->not->toContain('<p>');
});

it('tells the owner and the domain admins when the DNS of a domain fails', function (): void {
    Notification::fake();
    $tenant = billingTenant();
    $tenant->forceFill(['owner_name' => 'Mona', 'owner_email' => 'owner@megz.test'])->save();
    $admin = billingAdmin(['vendors.domains.view']);
    $other = billingAdmin(['finance.invoices.view']);
    $request = DomainRequest::factory()->create(['tenant_id' => $tenant->id, 'host' => 'shop.megz.test']);

    event(new DomainDnsFailed($request));

    Notification::assertSentOnDemand(DomainDnsFailedNotification::class, fn (DomainDnsFailedNotification $mail, array $channels, object $notifiable): bool => $mail->domain === 'shop.megz.test'
        && $notifiable->routes['mail'] === 'owner@megz.test'
        && $mail->mailTemplate($notifiable) === EmailTemplateEvent::VendorDomainDnsFailed);
    Notification::assertSentTo($admin, DomainAlertNotification::class, fn (DomainAlertNotification $alert, array $channels): bool => ! in_array('mail', $channels, true));
    Notification::assertNotSentTo($other, DomainAlertNotification::class);
});
