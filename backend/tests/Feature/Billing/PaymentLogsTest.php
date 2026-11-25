<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Events\PaymentRefunded;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/BillingHelpers.php';

/*
| Payment logs (ADM-055 / 055a): permissions, list filters, detail, status changes
| (capture pays the invoice, refund marks it refunded and requests the affiliate reversal).
*/

it('protects the payment log endpoints', function (): void {
    $log = PaymentLog::factory()->create();

    $this->get($this->centralUrl('/admin/finance/payment-logs'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(billingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/finance/payment-logs'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/finance/payment-logs/data'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}"))->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}/status"), ['status' => 'captured'])->assertForbidden();
});

it('lets view-only admins read but not change status', function (): void {
    $log = PaymentLog::factory()->create();
    $this->actingAs(billingAdmin(['finance.payment-logs.view']), 'admin');

    $this->get($this->centralUrl("/admin/finance/payment-logs/{$log->id}"))->assertOk()->assertSee(__('billing::payments.no_permission'));
    $this->patchJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}/status"), ['status' => 'captured'])->assertForbidden();
});

it('lists payment logs with gateway and status filters', function (): void {
    $this->actingAs(billingAdmin(['finance.payment-logs.view']), 'admin');
    $tenant = billingTenant('Nile Crafts');
    PaymentLog::factory()->create(['tenant_id' => $tenant->id, 'gateway' => PaymentGateway::Paymob, 'status' => PaymentLogStatus::Failed]);
    $stripe = PaymentLog::factory()->create(['gateway' => PaymentGateway::Stripe]);

    $this->get($this->centralUrl('/admin/finance/payment-logs'))->assertOk()->assertSee(route('admin.finance.payment-logs.data'), false);

    expect($this->getJson($this->centralUrl('/admin/finance/payment-logs/data?draw=1&start=0&length=10'))->json('recordsTotal'))->toBe(2)
        ->and($this->getJson($this->centralUrl('/admin/finance/payment-logs/data?draw=1&start=0&length=10&gateway=paymob'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/finance/payment-logs/data?draw=1&start=0&length=10&status=failed&gateway=stripe'))->json('recordsFiltered'))->toBe(0)
        ->and($this->getJson($this->centralUrl('/admin/finance/payment-logs/data?draw=1&start=0&length=10&search[value]='.$stripe->reference()))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($this->centralUrl('/admin/finance/payment-logs/data?draw=1&start=0&length=10&search[value]=Nile'))->json('recordsFiltered'))->toBe(1);
});

it('shows the payment log detail with payload and allowed next statuses', function (): void {
    $this->actingAs(billingAdmin(['finance.payment-logs.manage']), 'admin');
    $log = PaymentLog::factory()->create(['tenant_id' => billingTenant('GearUp')->id]);

    $this->get($this->centralUrl("/admin/finance/payment-logs/{$log->id}"))
        ->assertOk()
        ->assertSee($log->reference())
        ->assertSee('GearUp')
        ->assertSee('charge.pending')
        ->assertSee(route('admin.finance.payment-logs.status', $log->id), false);
});

it('captures a payment, pays its invoice and activates the subscription', function (): void {
    Event::fake([PaymentCaptured::class]);
    $this->actingAs(billingAdmin(['finance.payment-logs.manage']), 'admin');
    $tenant = billingTenant();
    $subscription = Subscription::factory()->status(SubscriptionStatus::PastDue)->create(['tenant_id' => $tenant->id, 'current_period_ends_at' => now()->subDay()]);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'billable_type' => 'subscription', 'billable_id' => $subscription->id]);
    $log = PaymentLog::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id]);

    $this->patchJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}/status"), ['status' => 'captured', 'note' => 'Confirmed by gateway'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($log->paid_at)->not->toBeNull()
        ->and($log->note)->toBe('Confirmed by gateway')
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->payment_method)->toBe('stripe')
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
    Event::assertDispatched(PaymentCaptured::class);
});

it('refunds a captured payment and requests the affiliate reversal', function (): void {
    Event::fake([PaymentRefunded::class, AffiliateConversionReversalRequested::class]);
    $this->actingAs(billingAdmin(['finance.payment-logs.manage']), 'admin');
    $tenant = billingTenant();
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => InvoiceStatus::Paid, 'paid_at' => now()]);
    $log = PaymentLog::factory()->status(PaymentLogStatus::Captured)->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount_minor' => 14900]);

    $this->patchJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}/status"), ['status' => 'refunded', 'note' => 'Customer request'])->assertOk();

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Refunded)
        ->and($log->refunded_at)->not->toBeNull()
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Refunded);

    Event::assertDispatched(PaymentRefunded::class, fn (PaymentRefunded $event): bool => $event->paymentLog->is($log));
    Event::assertDispatched(AffiliateConversionReversalRequested::class, fn (AffiliateConversionReversalRequested $event): bool => $event->paymentLogId === $log->id
        && $event->tenantId === $tenant->id
        && $event->refundedAmountMinor === 14900
        && $event->currency === 'USD'
        && $event->reason === 'Customer request');

    $this->get($this->centralUrl("/admin/finance/payment-logs/{$log->id}"))->assertOk()->assertSee(__('billing::payments.refund_reversal_note'));
});

it('validates and guards the status change', function (): void {
    $this->actingAs(billingAdmin(['finance.payment-logs.manage']), 'admin');
    $log = PaymentLog::factory()->create();

    $this->patchJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}/status"), ['status' => 'lost'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->patchJson($this->centralUrl("/admin/finance/payment-logs/{$log->id}/status"), ['status' => 'refunded'])
        ->assertUnprocessable()
        ->assertJson(['success' => false]);

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Pending);
});
