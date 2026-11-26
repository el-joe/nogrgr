<?php

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require_once __DIR__.'/BillingHelpers.php';
require_once __DIR__.'/../Platform/Registration/SignUpHelpers.php';

/*
| Decision #78 b: vendor subscription (VEN-060) payments with the shared inline Stripe card.
| The modal posts as JSON → PaymentIntent client secret + return URL → Stripe.js confirms in
| the page → the result URL reads the intent back (no webhook). A plain form post still
| goes to the hosted checkout.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->store = $this->createTenant('inline-'.Str::lower(Str::random(6)), ['name' => 'Inline Store']);
    $this->owner = $this->createVendorOwner($this->store);
    $plan = Plan::factory()->create(['name' => 'Growth', 'monthly_price_minor' => 5900, 'yearly_price_minor' => 58800]);
    $this->subscription = Subscription::factory()->create(['tenant_id' => $this->store->id, 'plan_id' => $plan->id, 'amount_minor' => 5900]);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->store, 'admin/finance/subscription'.($path === '' ? '' : '/'.$path));
    registrationGateway(PaymentGatewayCode::Stripe);
    $this->actingAsVendor($this->owner, $this->store);
});

it('renders the inline card with the publishable key in the checkout modals', function (): void {
    $this->get(($this->url)())
        ->assertOk()
        ->assertSee('data-stripe-card', false)
        ->assertSee('data-publishable-key="pk_test_1"', false)
        ->assertDontSee('sk_test_1')
        ->assertDontSee('js.stripe.com');
});

it('renews with the inline card: intent on start, captured from the intent on the result URL', function (): void {
    $intent = ['id' => 'pi_renew_1', 'object' => 'payment_intent', 'client_secret' => 'pi_renew_1_secret', 'amount' => 5900, 'currency' => 'usd'];
    Http::fake([
        'https://api.stripe.com/v1/payment_intents' => Http::response($intent + ['status' => 'requires_payment_method']),
        'https://api.stripe.com/v1/payment_intents/pi_renew_1' => Http::response($intent + ['status' => 'succeeded', 'amount_received' => 5900]),
    ]);

    $response = $this->postJson(($this->url)('checkout'), ['action' => 'renew', 'gateway' => 'stripe'])
        ->assertOk()
        ->assertJsonPath('data.inline.client_secret', 'pi_renew_1_secret')
        ->assertJsonPath('data.inline.publishable_key', 'pk_test_1');

    $log = PaymentLog::query()->withoutGlobalScopes()->sole();
    expect($log->purpose)->toBe(PaymentPurpose::Renewal)
        ->and($log->gateway_reference)->toBe('pi_renew_1')
        ->and((string) $response->json('data.inline.return_url'))->toContain("payments/{$log->id}/result");
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.com/v1/payment_intents' && (int) $request['amount'] === 5900 && $request['currency'] === 'usd');

    $this->get((string) $response->json('data.inline.return_url'))
        ->assertOk()
        ->assertSee('data-payment-state="paid"', false);

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($log->payload['transaction_id'])->toBe('pi_renew_1')
        ->and($this->subscription->refresh()->renewals_count)->toBe(1);
});

it('answers JSON errors to the card and keeps the hosted checkout for plain posts', function (): void {
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_plain', 'url' => 'https://checkout.stripe.com/c/pay/cs_plain'])]);

    $this->postJson(($this->url)('checkout'), ['action' => 'renew', 'gateway' => 'tap'])->assertUnprocessable()->assertJsonValidationErrors('gateway');
    $this->post(($this->url)('checkout'), ['action' => 'renew', 'gateway' => 'stripe'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_plain');

    expect(PaymentLog::query()->withoutGlobalScopes()->sole()->payload)->not->toHaveKey('inline');
});
