<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionRenewalService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require_once __DIR__.'/BillingHelpers.php';
require_once __DIR__.'/../Platform/Registration/SignUpHelpers.php';

/*
| VEN-060…060d Vendor › Subscription & balance: overview, history + invoices, ledger
| transactions, renew / change plan / pay invoice → hosted checkout → result page.
*/

function fakeVendorStripe(int $amountMinor, string $paymentStatus = 'paid'): void
{
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions*' => function (HttpRequest $request) use ($paymentStatus, $amountMinor) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_vendor', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_vendor']);
            }

            return Http::response(['id' => 'cs_test_vendor', 'status' => $paymentStatus === 'paid' ? 'complete' : 'open', 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_vendor', 'amount_total' => $amountMinor, 'currency' => 'usd']);
        },
    ]);
}

function vendorLedgerRow(Tenant|int $tenant, int $net, string $description): LedgerTransaction
{
    return LedgerTransaction::withoutTenantScope()->create([
        'reference' => 'TX-'.Str::upper(Str::random(8)),
        'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
        'source' => LedgerSource::Order,
        'direction' => $net >= 0 ? LedgerDirection::Credit : LedgerDirection::Debit,
        'amount' => abs($net),
        'net_amount' => $net,
        'balance_after' => $net,
        'currency' => 'USD',
        'description' => $description,
        'idempotency_key' => 'test-'.Str::random(20),
        'occurred_at' => now(),
    ]);
}

beforeEach(function (): void {
    Notification::fake();
    $this->store = $this->createTenant('megz-'.Str::lower(Str::random(6)), ['name' => 'Megz Store']);
    $this->owner = $this->createVendorOwner($this->store, ['email' => 'owner@megz.test']);
    $this->growth = Plan::factory()->create(['name' => 'Growth', 'monthly_price_minor' => 5900, 'yearly_price_minor' => 58800, 'limits' => ['products' => 5000, 'staff' => 3]]);
    $this->pro = Plan::factory()->create(['name' => 'Pro', 'monthly_price_minor' => 14900, 'yearly_price_minor' => 149000]);
    $this->subscription = Subscription::factory()->create(['tenant_id' => $this->store->id, 'plan_id' => $this->growth->id, 'amount_minor' => 5900]);
    $this->other = billingTenant('Other Store');
    $this->foreignSubscription = Subscription::factory()->create(['tenant_id' => $this->other->id, 'plan_id' => $this->pro->id]);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->store, 'admin/finance/subscription'.($path === '' ? '' : '/'.$path));
    $this->dt = fn (string $path, string $query = '') => $this->getJson(($this->url)($path.'?draw=1&start=0&length=25'.$query))->assertOk()->json();
    $this->gateway = function (bool $vendorPayments = false): void {
        $gateway = registrationGateway(PaymentGatewayCode::Stripe);

        if ($vendorPayments) {
            $gateway->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret']]);
        }
    };
});

it('sends guests to the vendor login', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->store, 'login'));
});

it('needs finance.subscription.view to read and finance.subscription.manage to pay', function (): void {
    [$none, $viewer] = $this->store->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'finance.subscription.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->store);
    foreach (['', 'history/data', 'invoices/data', 'transactions/data'] as $path) {
        $this->getJson(($this->url)($path))->assertForbidden();
    }

    $this->actingAsVendor($viewer, $this->store);
    $this->get(($this->url)())->assertOk()->assertDontSee(__('billing::vendor_subscription.actions.renew_now'));
    $this->post(($this->url)('checkout'), ['action' => 'renew', 'gateway' => 'stripe'])->assertForbidden();
    $this->get(($this->url)('payments/1/result'))->assertForbidden();
});

it('shows the plan, usage vs limits, balance and latest transactions', function (): void {
    vendorLedgerRow($this->store, 6051, 'Order share ORD-10431');
    vendorLedgerRow($this->other, 99999, 'Foreign order share');
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)())
        ->assertOk()
        ->assertSee(__('billing::vendor_subscription.title'))
        ->assertSee('Growth')
        ->assertSee('$59.00')
        ->assertSee('1 / 3', false)
        ->assertSee('data-usage="products"', false)
        ->assertSee('Order share ORD-10431')
        ->assertSee('+$60.51')
        ->assertDontSee('Foreign order share')
        ->assertSee(__('billing::vendor_subscription.actions.renew_now'))
        ->assertSee(__('billing::vendor_subscription.no_gateways_title'));

    $this->get(($this->url)().'?tab=history')->assertOk()->assertSee(($this->url)('history/data'), false)->assertSee(($this->url)('invoices/data'), false);
    $this->get(($this->url)().'?tab=transactions')->assertOk()->assertSee(($this->url)('transactions/data'), false);
});

it('shows the empty state when the store has no plan', function (): void {
    $this->subscription->update(['status' => SubscriptionStatus::Expired]);
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)())
        ->assertOk()
        ->assertSee(__('billing::vendor_subscription.plan.empty_title'))
        ->assertSee(__('billing::vendor_subscription.recent.empty_title'))
        ->assertDontSee(__('billing::vendor_subscription.actions.renew_now'));
});

it('lists only the store\'s subscriptions, invoices and ledger transactions', function (): void {
    $invoice = Invoice::factory()->create(['tenant_id' => $this->store->id, 'number' => 'INV-OWN-0001', 'status' => InvoiceStatus::Unpaid, 'billable_type' => 'subscription', 'billable_id' => $this->subscription->id]);
    Invoice::factory()->create(['tenant_id' => $this->other->id, 'number' => 'INV-FOREIGN-01']);
    vendorLedgerRow($this->store, 4120, 'Own share');
    vendorLedgerRow($this->other, 100, 'Foreign share');
    $this->actingAsVendor($this->owner, $this->store);

    $history = ($this->dt)('history/data');
    expect($history['recordsTotal'])->toBe(1)
        ->and($history['data'][0]['plan'])->toContain('Growth')
        ->and($history['data'][0]['invoice'])->toContain('INV-OWN-0001');

    $invoices = ($this->dt)('invoices/data');
    expect(collect($invoices['data'])->pluck('number')->implode(' '))->toContain('INV-OWN-0001')->not->toContain('INV-FOREIGN-01')
        ->and($invoices['data'][0]['actions'])->toContain('data-invoice-pay')->toContain($this->tenantUrl($this->store, "admin/finance/invoices/{$invoice->id}/print"));

    $transactions = ($this->dt)('transactions/data', '&tenant_id='.$this->other->id);
    expect($transactions['recordsTotal'])->toBe(1)
        ->and($transactions['data'][0]['entry'])->toContain('Own share');
});

it('prints the store\'s own invoice only', function (): void {
    $own = Invoice::factory()->create(['tenant_id' => $this->store->id, 'number' => 'INV-PRINT-0001']);
    $foreign = Invoice::factory()->create(['tenant_id' => $this->other->id]);
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)("invoices/{$own->id}/print"))->assertOk()->assertSee('INV-PRINT-0001')->assertSee('data-autoprint', false);
    $this->get(($this->url)("invoices/{$foreign->id}/print"))->assertNotFound();
});

it('validates the checkout', function (): void {
    ($this->gateway)();
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('checkout'), [])->assertSessionHasErrors(['action', 'gateway']);
    $this->post(($this->url)('checkout'), ['action' => 'renew', 'gateway' => 'tap'])->assertSessionHasErrors('gateway');
    $this->post(($this->url)('checkout'), ['action' => 'change', 'gateway' => 'stripe'])->assertSessionHasErrors(['plan_id', 'term']);
    $this->post(($this->url)('checkout'), ['action' => 'change', 'gateway' => 'stripe', 'plan_id' => 999999, 'term' => 'weekly'])->assertSessionHasErrors(['plan_id', 'term']);
    $this->post(($this->url)('checkout'), ['action' => 'invoice', 'gateway' => 'stripe'])->assertSessionHasErrors('invoice_id');

    expect(PaymentLog::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('renews through the hosted checkout and shows the paid result', function (): void {
    ($this->gateway)();
    fakeVendorStripe(5900);
    $this->actingAsVendor($this->owner, $this->store);
    $this->get(($this->url)())->assertOk()->assertSee('data-plan-prices', false)->assertSee('value="stripe"', false);

    $this->post(($this->url)('checkout'), ['action' => 'renew', 'gateway' => 'stripe'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_vendor');

    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->purpose)->toBe(PaymentPurpose::Renewal)
        ->and($log->tenant_id)->toBe($this->store->id)
        ->and($log->amount_minor)->toBe(5900)
        ->and((int) $log->payload['subscription_id'])->toBe($this->subscription->id);

    $this->get(($this->url)("payments/{$log->id}/result"))
        ->assertOk()
        ->assertSee(__('billing::vendor_subscription.result.paid_title', ['plan' => 'Growth']))
        ->assertSee('data-payment-state="paid"', false);

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($this->subscription->refresh()->renewals_count)->toBe(1);
});

it('changes the plan once the payment is captured', function (): void {
    ($this->gateway)();
    fakeVendorStripe(149000);
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('checkout'), ['action' => 'change', 'gateway' => 'stripe', 'plan_id' => $this->pro->id, 'term' => 'yearly'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_vendor');

    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->amount_minor)->toBe(149000)
        ->and((int) $log->payload['plan_id'])->toBe($this->pro->id)
        ->and($log->payload['term'])->toBe('yearly');

    $this->get(($this->url)("payments/{$log->id}/result"))
        ->assertOk()
        ->assertSee(__('billing::vendor_subscription.result.paid_title', ['plan' => 'Pro']));

    $current = Subscription::query()->withoutGlobalScopes()->where('tenant_id', $this->store->id)->latest('id')->first();
    expect($current->plan_id)->toBe($this->pro->id)
        ->and($current->status)->toBe(SubscriptionStatus::Active)
        ->and($current->amount_minor)->toBe(149000)
        ->and($this->subscription->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($current->latestInvoice()->withoutGlobalScopes()->first()?->status)->toBe(InvoiceStatus::Paid);

    // A replayed capture never subscribes twice.
    app(SubscriptionRenewalService::class)->applyCaptured($log->refresh());
    expect(Subscription::query()->withoutGlobalScopes()->where('tenant_id', $this->store->id)->count())->toBe(2);
});

it('refuses to "change" to the plan and term the store already has', function (): void {
    ($this->gateway)();
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('checkout'), ['action' => 'change', 'gateway' => 'stripe', 'plan_id' => $this->growth->id, 'term' => 'monthly'])
        ->assertRedirect(($this->url)())
        ->assertSessionHas('error', __('billing::vendor_subscription.errors.same_plan'));
});

it('reports a cancelled checkout without changing the plan', function (): void {
    ($this->gateway)();
    fakeVendorStripe(14900, 'unpaid');
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('checkout'), ['action' => 'change', 'gateway' => 'stripe', 'plan_id' => $this->pro->id, 'term' => 'monthly']);
    $log = PaymentLog::query()->withoutGlobalScopes()->sole();

    $this->get(($this->url)("payments/{$log->id}/cancel"))
        ->assertOk()
        ->assertSee(__('billing::vendor_subscription.result.cancelled'))
        ->assertSee('data-payment-state="failed"', false);

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Failed)
        ->and($this->subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(Subscription::query()->withoutGlobalScopes()->where('tenant_id', $this->store->id)->count())->toBe(1);
});

it('pays an unpaid invoice of the store through the vendor-payments checkout', function (): void {
    ($this->gateway)(vendorPayments: true);
    $invoice = Invoice::factory()->create(['tenant_id' => $this->store->id, 'number' => 'INV-PAY-0001', 'status' => InvoiceStatus::Unpaid]);
    $foreign = Invoice::factory()->create(['tenant_id' => $this->other->id, 'status' => InvoiceStatus::Unpaid]);
    fakeVendorStripe($invoice->total()->minor);
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)())->assertOk()->assertSee('INV-PAY-0001')->assertSee('data-unpaid-invoice', false);

    $this->post(($this->url)('checkout'), ['action' => 'invoice', 'gateway' => 'stripe', 'invoice_id' => $foreign->id])
        ->assertRedirect(($this->url)())
        ->assertSessionHas('error', __('billing::vendor_subscription.errors.invoice_unavailable'));

    $this->post(($this->url)('checkout'), ['action' => 'invoice', 'gateway' => 'stripe', 'invoice_id' => $invoice->id])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_vendor');

    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->purpose)->toBe(PaymentPurpose::Invoice)->and($log->invoice_id)->toBe($invoice->id);

    $this->get(($this->url)("payments/{$log->id}/result"))
        ->assertOk()
        ->assertSee(__('billing::vendor_subscription.result.paid_invoice_title'))
        ->assertSee('INV-PAY-0001');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
});

it('keeps the result pages to the store\'s own payments', function (): void {
    $foreignLog = PaymentLog::factory()->create(['tenant_id' => $this->other->id, 'purpose' => PaymentPurpose::Renewal, 'payload' => ['subscription_id' => $this->foreignSubscription->id]]);
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)("payments/{$foreignLog->id}/result"))->assertNotFound();
    $this->get(($this->url)("payments/{$foreignLog->id}/cancel"))->assertNotFound();
    $this->get(($this->url)('payments/999999/result'))->assertNotFound();
});
