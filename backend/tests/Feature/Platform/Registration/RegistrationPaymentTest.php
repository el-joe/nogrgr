<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Owner;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Platform\DTOs\RegistrationStoreData;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Notifications\RegistrationLinkNotification;
use App\Modules\Platform\Notifications\RegistrationPaymentReceiptNotification;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Services\VendorRegistrationService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

require_once __DIR__.'/SignUpHelpers.php';
require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Paid sign-up: WEB-016c payment step, WEB-019 redirect, WEB-019a success, WEB-019b
| failed / cancelled, Stripe Checkout + PayPal Orders v2 (Http::fake), webhooks
| (signature, replay idempotency) and the prepaid plan settled at provisioning.
*/

const STRIPE_SESSIONS = 'https://api.stripe.com/v1/checkout/sessions*';

beforeEach(function (): void {
    Notification::fake();
});

function paidRegistration(): VendorRegistration
{
    signUpAndChoose(paidPlan(), 'paid')->assertRedirect(route('central.register.payment'));

    return VendorRegistration::query()->sole();
}

function returnUrl(string $route, VendorRegistration $registration, PaymentLog $log): string
{
    return URL::temporarySignedRoute($route, now()->addHour(), ['registration' => $registration->id, 'payment' => $log->id]);
}

function fakeStripe(string $paymentStatus = 'paid', string $status = 'complete'): void
{
    Http::fake([
        STRIPE_SESSIONS => function (HttpRequest $request) use ($paymentStatus, $status) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);
            }

            return Http::response(['id' => 'cs_test_123', 'status' => $status, 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_123', 'amount_total' => 9900, 'currency' => 'usd']);
        },
    ]);
}

function startCheckout(string $gateway = 'stripe'): PaymentLog
{
    $response = test()->post(route('central.register.payment.store'), ['gateway' => $gateway]);
    $log = PaymentLog::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/register/payment/')->toContain('/charge?');

    return $log;
}

function stripeEvent(string $type, array $session, ?string $id = null): string
{
    return (string) json_encode(['id' => $id ?? 'evt_'.Str::random(10), 'type' => $type, 'data' => ['object' => $session]]);
}

describe('payment step', function (): void {
    it('lists only active registration gateways that have a driver, with the order summary', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        registrationGateway(PaymentGatewayCode::Tap, ['public_key' => 'pk', 'secret_key' => 'sk']);
        config(['billing.tax_rate_bp' => 1500]);

        paidRegistration();

        $this->get(route('central.register.payment'))
            ->assertOk()
            ->assertSee('value="stripe"', false)
            ->assertDontSee('value="tap"', false)
            ->assertSee('$99.00')
            ->assertSee('$14.85')
            ->assertSee('$113.85')
            ->assertDontSee('coupon');
    });

    it('shows the empty state when no gateway can take the payment', function (): void {
        paidRegistration();

        $this->get(route('central.register.payment'))->assertOk()->assertSee(__('platform::signup.payment.none_title'));
        $this->post(route('central.register.payment.store'), ['gateway' => 'stripe'])->assertSessionHas('error');
    });

    it('redirects a trial registration away from the payment step', function (): void {
        signUpAndChoose(paidPlan(14), 'trial');

        $this->get(route('central.register.payment'))->assertRedirect(route('central.register.verify'));
    });
});

describe('stripe checkout', function (): void {
    it('pays, settles the payment log and moves the registration to e-mail verification', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe();
        $registration = paidRegistration();

        $log = startCheckout();

        expect($log->status)->toBe(PaymentLogStatus::Pending)
            ->and($log->purpose)->toBe(PaymentPurpose::Registration)
            ->and($log->amount_minor)->toBe(9900)
            ->and($log->tenant_id)->toBeNull()
            ->and($log->gateway_reference)->toBe('cs_test_123')
            ->and($log->payload['registration_id'])->toBe($registration->id)
            ->and($log->payload['fee_minor'])->toBe(317);

        Http::assertSent(function (HttpRequest $request) use ($log): bool {
            parse_str($request->body(), $form);

            return $request->method() === 'POST'
                && ($form['line_items'][0]['price_data']['unit_amount'] ?? null) === '9900'
                && ($form['client_reference_id'] ?? null) === $log->reference()
                && $request->hasHeader('Idempotency-Key', 'checkout-'.$log->reference())
                && str_contains((string) ($form['success_url'] ?? ''), 'signature=');
        });

        $this->get(returnUrl('central.register.payment.charge', $registration, $log))
            ->assertOk()
            ->assertSee('https://checkout.stripe.com/c/pay/cs_test_123');

        $this->get(returnUrl('central.register.payment.success', $registration, $log))
            ->assertOk()
            ->assertSee(__('platform::signup.payment.success_title'))
            ->assertSee($log->reference());

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured)
            ->and($log->fresh()->paid_at)->not->toBeNull()
            ->and($registration->fresh()->status)->toBe(RegistrationStatus::EmailUnverified)
            ->and($registration->fresh()->paymentLogId())->toBe($log->id);

        Notification::assertSentOnDemand(RegistrationPaymentReceiptNotification::class);
        Notification::assertSentOnDemand(RegistrationLinkNotification::class);

        $this->get(route('central.register.verify'))->assertOk();
    });

    it('shows the failed page with retry when the payer cancels', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe(paymentStatus: 'unpaid', status: 'open');
        $registration = paidRegistration();
        $log = startCheckout();

        $this->get(returnUrl('central.register.payment.cancel', $registration, $log))
            ->assertOk()
            ->assertSee(__('platform::signup.payment.cancelled_title'))
            ->assertSee(route('central.register.payment'), false);

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Failed)
            ->and($registration->fresh()->status)->toBe(RegistrationStatus::PaymentFailed);

        $this->get(route('central.register.payment'))->assertOk();
        $retry = startCheckout();
        expect($retry->id)->not->toBe($log->id);
    });

    it('keeps a still-open checkout pending on the success page', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe(paymentStatus: 'unpaid', status: 'open');
        $registration = paidRegistration();
        $log = startCheckout();

        $this->get(returnUrl('central.register.payment.success', $registration, $log))
            ->assertOk()
            ->assertSee(__('platform::signup.payment.processing_title'));

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Pending);
    });

    it('rejects tampered or expired return links', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe();
        $registration = paidRegistration();
        $log = startCheckout();

        $this->get(returnUrl('central.register.payment.success', $registration, $log).'x')
            ->assertOk()->assertSee(__('platform::signup.payment.invalid_title'));

        $expired = URL::temporarySignedRoute('central.register.payment.success', now()->subMinute(), ['registration' => $registration->id, 'payment' => $log->id]);
        $this->get($expired)->assertOk()->assertSee(__('platform::signup.payment.invalid_title'));

        $other = VendorRegistration::factory()->create();
        $this->get(returnUrl('central.register.payment.success', $other, $log))->assertOk()->assertSee(__('platform::signup.payment.invalid_title'));

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Pending);
    });

    it('settles from the webhook once and ignores replays', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe();
        $registration = paidRegistration();
        $log = startCheckout();
        Event::fake([PaymentCaptured::class]);

        $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_123', 'payment_status' => 'paid'], 'evt_replay_1');
        $send = fn () => $this->call('POST', route('central.webhooks.payments', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => stripeSignature($payload), 'CONTENT_TYPE' => 'application/json'], $payload);

        $send()->assertOk()->assertJson(['received' => true, 'result' => 'processed']);
        $send()->assertOk()->assertJson(['result' => 'duplicate']);

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured);
        Event::assertDispatchedTimes(PaymentCaptured::class, 1);

        // A different event about the same, already captured checkout changes nothing.
        $again = stripeEvent('checkout.session.completed', ['id' => 'cs_test_123', 'payment_status' => 'paid']);
        $this->call('POST', route('central.webhooks.payments', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => stripeSignature($again), 'CONTENT_TYPE' => 'application/json'], $again)->assertOk();
        Event::assertDispatchedTimes(PaymentCaptured::class, 1);
        expect($registration->fresh()->status)->toBe(RegistrationStatus::PaymentPending); // listener faked here
    });

    it('advances the registration exactly once when webhook and return both arrive', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe();
        $registration = paidRegistration();
        $log = startCheckout();

        $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_test_123', 'payment_status' => 'paid']);
        $this->call('POST', route('central.webhooks.payments', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => stripeSignature($payload), 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $this->get(returnUrl('central.register.payment.success', $registration, $log))->assertOk()->assertSee(__('platform::signup.payment.success_title'));

        expect($registration->fresh()->status)->toBe(RegistrationStatus::EmailUnverified)
            ->and($registration->fresh()->link_sent_count)->toBe(1);
        Notification::assertSentOnDemandTimes(RegistrationPaymentReceiptNotification::class, 1);
    });

    it('rejects webhooks with a bad or stale signature without touching the CSRF check', function (): void {
        registrationGateway(PaymentGatewayCode::Stripe);
        $payload = stripeEvent('checkout.session.completed', ['id' => 'cs_x', 'payment_status' => 'paid']);

        $this->call('POST', route('central.webhooks.payments', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_wrong'), 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertStatus(400);
        $this->call('POST', route('central.webhooks.payments', 'stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, time: time() - 3600), 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertStatus(400);
        $this->postJson(route('central.webhooks.payments', 'nope'))->assertNotFound();
    });
});

describe('paypal checkout', function (): void {
    beforeEach(function (): void {
        registrationGateway(PaymentGatewayCode::Paypal);
    });

    function fakePaypal(array $capture = [], bool $verified = true): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'A21-token', 'expires_in' => 32000]),
            '*/v2/checkout/orders/ORDER-1/capture' => Http::response($capture !== [] ? $capture : [
                'id' => 'ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-9', 'status' => 'COMPLETED', 'amount' => ['value' => '99.00', 'currency_code' => 'USD']]]]]],
            ], $capture['__status'] ?? 201),
            '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1']]], 201),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => $verified ? 'SUCCESS' : 'FAILURE']),
        ]);
    }

    it('creates an order, captures it on return and ignores the appended PayPal query', function (): void {
        fakePaypal();
        $registration = paidRegistration();
        $log = startCheckout('paypal');

        expect($log->gateway_reference)->toBe('ORDER-1');
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v2/checkout/orders')
            && str_starts_with($request->url(), 'https://api-m.sandbox.paypal.com')
            && $request['purchase_units'][0]['amount']['value'] === '99.00'
            && $request['purchase_units'][0]['custom_id'] === $log->reference());

        $this->get(returnUrl('central.register.payment.success', $registration, $log).'&token=ORDER-1&PayerID=PAYER1')
            ->assertOk()
            ->assertSee(__('platform::signup.payment.success_title'));

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured)
            ->and($log->fresh()->payload['transaction_id'])->toBe('CAP-9')
            ->and($registration->fresh()->status)->toBe(RegistrationStatus::EmailUnverified);
    });

    it('keeps an unapproved order pending', function (): void {
        fakePaypal(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_NOT_APPROVED']], '__status' => 422]);
        $registration = paidRegistration();
        $log = startCheckout('paypal');

        $this->get(returnUrl('central.register.payment.success', $registration, $log))->assertOk()->assertSee(__('platform::signup.payment.processing_title'));
        expect($log->fresh()->status)->toBe(PaymentLogStatus::Pending);
    });

    it('verifies webhooks with PayPal and settles a completed capture once', function (): void {
        fakePaypal();
        $registration = paidRegistration();
        $log = startCheckout('paypal');

        $payload = (string) json_encode(['id' => 'WH-EVT-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAP-9', 'status' => 'COMPLETED', 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']]]]);
        $headers = ['HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA', 'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/cert.pem', 'HTTP_PAYPAL_TRANSMISSION_ID' => 'tid', 'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig', 'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('central.webhooks.payments', 'paypal'), [], [], [], $headers, $payload)->assertOk()->assertJson(['result' => 'processed']);
        $this->call('POST', route('central.webhooks.payments', 'paypal'), [], [], [], $headers, $payload)->assertOk()->assertJson(['result' => 'duplicate']);

        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/notifications/verify-webhook-signature') && $request['webhook_id'] === 'WH-123');

        expect($log->fresh()->status)->toBe(PaymentLogStatus::Captured)
            ->and($registration->fresh()->status)->toBe(RegistrationStatus::EmailUnverified);
        Notification::assertSentOnDemandTimes(RegistrationPaymentReceiptNotification::class, 1);
    });

    it('rejects a webhook PayPal does not verify', function (): void {
        fakePaypal(verified: false);
        $payload = (string) json_encode(['id' => 'WH-EVT-2', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []]);
        $headers = ['HTTP_PAYPAL_AUTH_ALGO' => 'a', 'HTTP_PAYPAL_CERT_URL' => 'b', 'HTTP_PAYPAL_TRANSMISSION_ID' => 'c', 'HTTP_PAYPAL_TRANSMISSION_SIG' => 'd', 'HTTP_PAYPAL_TRANSMISSION_TIME' => 'e', 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('central.webhooks.payments', 'paypal'), [], [], [], $headers, $payload)->assertStatus(400);
    });
});

describe('provisioning a paid registration', function (): void {
    it('starts an active subscription whose invoice is settled by the registration payment', function (): void {
        cleanUpProvisionedTenants();
        Queue::fake();
        registrationGateway(PaymentGatewayCode::Stripe);
        fakeStripe();
        $registration = paidRegistration();
        $log = startCheckout();
        $this->get(returnUrl('central.register.payment.success', $registration, $log))->assertOk();

        $registration->refresh();
        $registration->forceFill(['owner_name' => 'Mona Ali', 'password' => 'secret-pass-123'])->save();
        $tenant = app(VendorRegistrationService::class)->complete($registration, new RegistrationStoreData('Mona Shop', 'mona-'.Str::lower(Str::random(6))));

        $owner = Owner::query()->where('email', 'founder@example.com')->sole();
        expect($tenant->owner_id)->toBe($owner->id)->and($registration->fresh()->owner_id)->toBe($owner->id);

        app(TenantProvisioningService::class)->run($tenant->fresh());

        $subscription = Subscription::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $invoice = Invoice::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        expect($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($invoice->status)->toBe(InvoiceStatus::Paid)
            ->and($invoice->payment_reference)->toBe('cs_test_123')
            ->and($log->fresh()->tenant_id)->toBe($tenant->id)
            ->and($log->fresh()->invoice_id)->toBe($invoice->id);
    });
});
