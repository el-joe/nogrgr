<?php

use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\StockReservation;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Reporting\Models\OrderFact;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Store API payments (P5 batch 2, decision #78 b): Stripe through the hosted Stripe
| Checkout redirect (never PaymentIntents / Stripe.js) and PayPal (redirect) with the
| store's own keys or the central fallback, signature-checked replay-safe webhooks,
| payment log per attempt, settlement on capture, COD.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    shopGateway($this->shop->tenant, PaymentGatewayCode::Paypal);
    $this->customer = shopCustomer($this->shop);
});

function shopStripeSession(bool $paid = false, int $amount = 6380): array
{
    return [
        'id' => 'cs_123', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        'status' => $paid ? 'complete' : 'open', 'payment_status' => $paid ? 'paid' : 'unpaid',
        'payment_intent' => $paid ? 'pi_123' : null, 'amount_total' => $amount, 'currency' => 'usd',
    ];
}

it('starts a hosted Stripe Checkout session with the central keys and logs the attempt', function (): void {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(shopStripeSession())]);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);

    $response = $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))
        ->assertCreated()
        ->assertJsonPath('data.redirect_url', 'https://checkout.stripe.com/c/pay/cs_123')
        ->assertJsonPath('data.payment.status', 'pending')
        ->assertJsonPath('data.payment.source', 'central')
        ->assertJsonPath('data.payment.amount.amount', 6380);

    // No client secret / publishable key ever reaches the store or the app.
    expect($response->json('data'))->not->toHaveKey('client_secret');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $request->hasHeader('Authorization', 'Bearer sk_central')
        && $request['mode'] === 'payment'
        && str_contains(urldecode($request->body()), 'line_items[0][price_data][unit_amount]=6380')
        && str_contains(urldecode($request->body()), 'line_items[0][price_data][currency]=usd')
        && str_contains((string) $request['success_url'], '/checkout/payment/stripe/'.$data['payment_order'].'?status=success'));
    Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/v1/payment_intents'));

    $order = $this->shop->tenant->run(fn () => Order::query()->sole());
    expect($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payment_gateway)->toBe('stripe');
});

it('uses the store own keys when the store connected its own account', function (): void {
    $this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->firstOrFail()->update([
        'use_own' => true, 'credentials' => ['publishable_key' => 'pk_own', 'secret_key' => 'sk_own', 'webhook_secret' => 'whsec_own'], 'connection_status' => GatewayConnection::Connected,
    ]));
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(shopStripeSession())]);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);

    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))
        ->assertCreated()
        ->assertJsonPath('data.payment.source', 'tenant')
        ->assertJsonPath('data.redirect_url', 'https://checkout.stripe.com/c/pay/cs_123');

    Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer sk_own'));
});

it('settles the order once from a signed Stripe Checkout webhook and ignores replays', function (): void {
    Notification::fake();
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(shopStripeSession())]);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2, $this->shop->own->id => 1]);
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->assertCreated();
    Event::fake([OrderPaid::class]);

    $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => shopStripeSession(true, 7880)]]);
    $headers = ['X-Tenant' => (string) $this->shop->tenant->id, 'Stripe-Signature' => shopStripeSignature($payload, 'whsec_central'), 'Content-Type' => 'application/json'];

    $this->call('POST', storeApi('payments/webhooks/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertOk()
        ->assertJsonPath('message', 'processed');

    $this->call('POST', storeApi('payments/webhooks/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertOk()
        ->assertJsonPath('message', 'duplicate');

    $orders = $this->shop->tenant->run(fn () => Order::query()->orderBy('id')->get());

    expect($orders->pluck('status')->unique()->all())->toBe([OrderStatus::Paid])
        ->and($orders->pluck('payment_status')->unique()->all())->toBe([PaymentStatus::Paid])
        ->and($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->status))->toBe(PaymentAttemptStatus::Paid)
        // The session's payment intent is kept: gateway refunds of webhook-settled payments need it.
        ->and($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->transaction_id))->toBe('pi_123')
        ->and($this->shop->tenant->run(fn () => $this->shop->own->fresh()?->stock))->toBe(4)
        ->and(StockReservation::withoutTenantScope()->orderBy('id')->pluck('status')->map->value->all())->toBe(['committed', 'deducted'])
        ->and(OrderFact::withoutTenantScope()->pluck('status')->unique()->all())->toBe(['paid']);

    Event::assertDispatchedTimes(OrderPaid::class, 2);
});

it('rejects webhooks with a bad signature', function (): void {
    $payload = json_encode(['id' => 'evt_bad', 'type' => 'checkout.session.completed', 'data' => ['object' => shopStripeSession(true)]]);
    $headers = ['X-Tenant' => (string) $this->shop->tenant->id, 'Stripe-Signature' => shopStripeSignature($payload, 'whsec_wrong'), 'Content-Type' => 'application/json'];

    $this->call('POST', storeApi('payments/webhooks/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertStatus(400);

    $this->postJson(storeApi('payments/webhooks/bitcoin'), [], shopHeaders($this->shop->tenant))->assertNotFound();
});

it('confirms a paid Stripe Checkout session on return and keeps its payment intent', function (): void {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(shopStripeSession()),
        'api.stripe.com/v1/checkout/sessions/cs_123' => Http::response(shopStripeSession(true)),
    ]);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $reference = $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->json('data.payment.reference');

    $this->postJson(storeApi('payments/'.$data['payment_order'].'/confirm'), ['reference' => $reference], shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.payment.status', 'paid')
        ->assertJsonPath('data.order.status', 'paid');

    expect($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->transaction_id))->toBe('pi_123');
});

it('marks a capture with the wrong amount as failed', function (): void {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(shopStripeSession()),
        'api.stripe.com/v1/checkout/sessions/cs_123' => Http::response(shopStripeSession(true, 100)),
    ]);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $reference = $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->json('data.payment.reference');

    $this->postJson(storeApi('payments/'.$data['payment_order'].'/confirm'), ['reference' => $reference], shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.payment.status', 'failed')
        ->assertJsonPath('data.payment.failure_reason', 'amount_mismatch')
        ->assertJsonPath('data.order.status', 'pending_payment')
        ->assertJsonPath('data.order.payment_status', 'failed');
});

it('captures a PayPal order on return', function (): void {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'PP-1', 'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PP-1/capture' => Http::response([
            'id' => 'PP-1', 'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['value' => '63.80', 'currency_code' => 'USD']]]]]],
        ]),
    ]);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], gateway: PaymentGatewayCode::Paypal);

    $start = $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'paypal'], shopHeaders($this->shop->tenant))
        ->assertCreated()
        ->assertJsonPath('data.redirect_url', 'https://paypal.test/approve')
        ->json('data');

    $this->postJson(storeApi('payments/'.$data['payment_order'].'/confirm'), ['reference' => $start['payment']['reference']], shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('message', 'Payment completed successfully.')
        ->assertJsonPath('data.order.status', 'paid');

    // Confirming again is a no-op (no second capture call).
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/confirm'), ['reference' => $start['payment']['reference']], shopHeaders($this->shop->tenant))->assertOk();
    Http::assertSentCount(4);

    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'paypal'], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'not_payable');
});

it('refuses gateways that are not listed and unknown orders', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);

    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'tap'], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'gateway_unavailable');

    $this->postJson(storeApi('payments/00000000-0000-0000-0000-000000000000/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->assertNotFound();
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), [], shopHeaders($this->shop->tenant))->assertJsonValidationErrors('gateway');
});

it('confirms cash on delivery server side and notifies customer, store and admins', function (): void {
    Notification::fake();
    $this->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['dashboard.view', 'orders.view']);
    $owner = $this->createVendorOwner($this->shop->tenant);
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);

    $orders = $this->shop->tenant->run(fn () => app(OrderLifecycleService::class)->confirmCashOnDelivery($data['checkout_group']));

    expect($orders->sole()->status)->toBe(OrderStatus::Processing)
        ->and($orders->sole()->payment_status)->toBe(PaymentStatus::CashOnDelivery)
        ->and(StockReservation::withoutTenantScope()->sole()->status)->toBe(StockReservationStatus::Committed);

    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn (OrderCustomerNotification $notification): bool => $notification->eventName === 'confirmation');
    Notification::assertSentTo($admin, OrderAlertNotification::class, fn (OrderAlertNotification $notification): bool => $notification->eventName === 'admin_order_paid');
    Notification::assertSentTo($this->shop->tenant->run(fn () => VendorStaff::query()->findOrFail($owner->id)), OrderAlertNotification::class, fn (OrderAlertNotification $notification): bool => $notification->forStore);

    $this->shop->tenant->run(function () use ($orders): void {
        $order = $orders->sole();
        app(OrderLifecycleService::class)->ship($order, 'TRK-1');
        app(OrderLifecycleService::class)->transition($order, OrderStatus::Delivered);
    });

    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn (OrderCustomerNotification $notification): bool => $notification->eventName === 'shipped' && $notification->variables['shipment.tracking_no'] === 'TRK-1');
    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn (OrderCustomerNotification $notification): bool => $notification->eventName === 'delivered');
    expect(OrderFact::withoutTenantScope()->sole()->status)->toBe('delivered');
});
