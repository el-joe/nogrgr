<?php

use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentAttemptStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Integrations\Models\GatewayCheckoutRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Storefront payments taken with the platform's gateway keys are notified to the central
| webhook endpoint: the event is verified with the central secret, routed to the store
| through gateway_checkout_routes and settled once (replays are duplicates).
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_central_1', 'object' => 'payment_intent', 'client_secret' => 'pi_central_1_secret', 'status' => 'requires_payment_method', 'amount' => 6380, 'amount_received' => 0, 'currency' => 'usd'])]);
});

function centralStripeEvent(string $eventId, string $secret, int $amount = 6380, string $intent = 'pi_central_1'): array
{
    $payload = json_encode(['id' => $eventId, 'type' => 'payment_intent.succeeded', 'data' => ['object' => [
        'id' => $intent, 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => $amount, 'amount_received' => $amount, 'currency' => 'usd', 'latest_charge' => 'ch_1',
    ]]]);

    return [$payload, ['Stripe-Signature' => shopStripeSignature((string) $payload, $secret), 'Content-Type' => 'application/json']];
}

it('records a central route when a payment starts on the platform keys', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->assertCreated();

    $route = GatewayCheckoutRoute::withoutTenantScope()->sole();
    $reference = $this->shop->tenant->run(fn () => OrderPayment::query()->sole()->reference);

    expect($route->gateway)->toBe('stripe')
        ->and($route->checkout_id)->toBe('pi_central_1')
        ->and($route->tenant_id)->toBe((int) $this->shop->tenant->id)
        ->and($route->payment_reference)->toBe($reference);
});

it('settles a central-keys storefront payment from the central webhook once', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->assertCreated();
    [$payload, $headers] = centralStripeEvent('evt_central_1', 'whsec_central');

    $this->call('POST', $this->centralUrl('/webhooks/payments/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertOk()
        ->assertJson(['received' => true, 'result' => 'processed']);

    $this->call('POST', $this->centralUrl('/webhooks/payments/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertOk()
        ->assertJson(['received' => true, 'result' => 'duplicate']);

    expect($this->shop->tenant->run(fn () => Order::query()->sole()->status))->toBe(OrderStatus::Paid)
        ->and($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->status))->toBe(PaymentAttemptStatus::Paid)
        ->and($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->transaction_id))->toBe('pi_central_1');
});

it('rejects central webhooks not signed with the central secret', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->assertCreated();
    [$payload, $headers] = centralStripeEvent('evt_forged', 'whsec_forged');

    $this->call('POST', $this->centralUrl('/webhooks/payments/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertStatus(400)
        ->assertJson(['received' => false]);

    expect($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->status))->toBe(PaymentAttemptStatus::Pending);
});

it('does not route payments made with the store own keys or unknown checkouts', function (): void {
    $this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->firstOrFail()->update([
        'use_own' => true, 'credentials' => ['publishable_key' => 'pk_own', 'secret_key' => 'sk_own', 'webhook_secret' => 'whsec_own'], 'connection_status' => GatewayConnection::Connected,
    ]));
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->postJson(storeApi('payments/'.$data['payment_order'].'/start'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant))->assertCreated();

    expect(GatewayCheckoutRoute::withoutTenantScope()->count())->toBe(0);

    // A forged route to an own-keys attempt is refused as well.
    GatewayCheckoutRoute::withoutTenantScope()->create(['gateway' => 'stripe', 'checkout_id' => 'pi_central_1', 'tenant_id' => $this->shop->tenant->id, 'payment_reference' => 'x']);
    [$payload, $headers] = centralStripeEvent('evt_own', 'whsec_central');

    $this->call('POST', $this->centralUrl('/webhooks/payments/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertOk()
        ->assertJson(['result' => 'ignored']);

    [$payload, $headers] = centralStripeEvent('evt_unknown', 'whsec_central', intent: 'pi_unknown');

    $this->call('POST', $this->centralUrl('/webhooks/payments/stripe'), [], [], [], $this->transformHeadersToServerVars($headers), $payload)
        ->assertOk()
        ->assertJson(['result' => 'ignored']);

    expect($this->shop->tenant->run(fn () => OrderPayment::query()->sole()->status))->toBe(PaymentAttemptStatus::Pending);
});
