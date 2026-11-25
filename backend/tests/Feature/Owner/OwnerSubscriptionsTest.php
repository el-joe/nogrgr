<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\SubscriptionRenewalService;
use App\Modules\Identity\Models\Owner;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/OwnerHelpers.php';
require_once __DIR__.'/../Platform/Registration/SignUpHelpers.php';

/*
| OWN-003 Subscriptions across all stores + renew through the hosted checkout.
*/

function fakeOwnerStripe(string $paymentStatus = 'paid'): void
{
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions*' => function (HttpRequest $request) use ($paymentStatus) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_renew', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_renew']);
            }

            return Http::response(['id' => 'cs_test_renew', 'status' => $paymentStatus === 'paid' ? 'complete' : 'open', 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_renew', 'amount_total' => 5900, 'currency' => 'usd']);
        },
    ]);
}

beforeEach(function (): void {
    Notification::fake();
    $this->owner = Owner::factory()->create(['email' => 'owner@example.test']);
    $this->store = ownerStore($this->owner, ['name' => 'Megz Store']);
    $this->subscription = ownerSubscription($this->store, ['current_period_ends_at' => now()->addDays(10)]);
    $this->foreignStore = ownerStore(Owner::factory()->create(), ['name' => 'Foreign Store']);
    $this->foreign = ownerSubscription($this->foreignStore);
    $this->actingAs($this->owner, 'owner');
});

it('renders the subscriptions page', function (): void {
    $this->get(route('owner.subscriptions.index'))
        ->assertOk()
        ->assertSee(__('billing::owner.subscriptions.title'))
        ->assertSee(route('owner.subscriptions.data'), false)
        ->assertSee(__('billing::owner.subscriptions.no_gateways_title'));
});

it('lists only the owner\'s subscriptions', function (): void {
    $rows = ownerRows('owner.subscriptions.data');

    expect(collect($rows)->pluck('id')->all())->toBe([$this->subscription->id])
        ->and($rows[0]['store'])->toContain('Megz Store')
        ->and($rows[0]['actions'])->toContain(route('owner.subscriptions.renew', $this->subscription->id));

    expect(ownerRows('owner.subscriptions.data', ['tenant_id' => $this->foreignStore->id]))->toBe([]);
});

it('shows the past-due alert with a pay action', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);
    $this->subscription->update(['status' => SubscriptionStatus::PastDue]);
    ownerInvoice($this->store, ['number' => 'INV-PAST-0412', 'billable_type' => 'subscription', 'billable_id' => $this->subscription->id]);

    $this->get(route('owner.subscriptions.index'))
        ->assertOk()
        ->assertSee('INV-PAST-0412')
        ->assertSee(__('billing::owner.subscriptions.pay_now'))
        ->assertSee('value="stripe"', false);
});

it('validates the renewal gateway', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);

    $this->post(route('owner.subscriptions.renew', $this->subscription->id), [])->assertSessionHasErrors('gateway');
    $this->post(route('owner.subscriptions.renew', $this->subscription->id), ['gateway' => 'tap'])->assertSessionHasErrors('gateway');
});

it('refuses to renew another owner\'s subscription', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);
    fakeOwnerStripe();

    $this->post(route('owner.subscriptions.renew', $this->foreign->id), ['gateway' => 'stripe'])->assertForbidden();
    expect(PaymentLog::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('does not renew a closed subscription online', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);
    $this->subscription->update(['status' => SubscriptionStatus::Expired]);

    $this->post(route('owner.subscriptions.renew', $this->subscription->id), ['gateway' => 'stripe'])
        ->assertRedirect(route('owner.subscriptions.index'))
        ->assertSessionHas('error');
});

it('renews through the hosted checkout and the return page', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);
    fakeOwnerStripe();
    $periodEnd = $this->subscription->current_period_ends_at->copy();

    $this->post(route('owner.subscriptions.renew', $this->subscription->id), ['gateway' => 'stripe'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_renew');

    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->purpose)->toBe(PaymentPurpose::Renewal)
        ->and($log->tenant_id)->toBe($this->store->id)
        ->and($log->amount_minor)->toBe(5900)
        ->and((int) $log->payload['subscription_id'])->toBe($this->subscription->id);

    $this->get(route('owner.subscriptions.payment.return', ['subscription' => $this->subscription->id, 'payment' => $log->id]))
        ->assertRedirect(route('owner.subscriptions.index'))
        ->assertSessionHas('success', __('billing::owner.subscriptions.renewed'));

    $this->subscription->refresh();
    expect($log->refresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($this->subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($this->subscription->renewals_count)->toBe(1)
        ->and($this->subscription->current_period_ends_at->toDateString())->toBe($periodEnd->addMonthNoOverflow()->toDateString());

    $invoice = Invoice::query()->withoutGlobalScopes()->where('type', InvoiceType::Renewal)->sole();
    expect($invoice->status)->toBe(InvoiceStatus::Paid);

    // A replayed capture never renews twice.
    app(SubscriptionRenewalService::class)->applyCaptured($log->refresh());
    expect($this->subscription->refresh()->renewals_count)->toBe(1);
});

it('reports a cancelled checkout without renewing', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);
    fakeOwnerStripe('unpaid');

    $this->post(route('owner.subscriptions.renew', $this->subscription->id), ['gateway' => 'stripe']);
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();

    $this->get(route('owner.subscriptions.payment.cancel', ['subscription' => $this->subscription->id, 'payment' => $log->id]))
        ->assertRedirect(route('owner.subscriptions.index'))
        ->assertSessionHas('error');

    expect($this->subscription->refresh()->renewals_count)->toBe(0);
});

it('keeps the return pages to the owner\'s own payment', function (): void {
    registrationGateway(PaymentGatewayCode::Stripe);
    fakeOwnerStripe();
    $this->post(route('owner.subscriptions.renew', $this->subscription->id), ['gateway' => 'stripe']);
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();

    $this->get(route('owner.subscriptions.payment.return', ['subscription' => $this->foreign->id, 'payment' => $log->id]))->assertForbidden();

    $other = ownerSubscription($this->store);
    $this->get(route('owner.subscriptions.payment.return', ['subscription' => $other->id, 'payment' => $log->id]))->assertNotFound();
});
