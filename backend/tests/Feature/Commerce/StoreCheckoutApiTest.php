<?php

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\DTOs\Cart\StockRequirement;
use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Jobs\ExpireUnpaidOrders;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CheckoutAttempt;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StockReservation;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Models\StoreCouponRedemption;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Commerce\Repositories\Contracts\StockReservationRepositoryContract;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Commerce\Services\Payments\StorefrontGatewayService;
use App\Modules\Commerce\Services\Stock\StockReservationService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Reporting\Models\OrderFact;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Store API checkout (P5 batch 2): options (addresses, summary, gateways filtered as spec
| 08), idempotent place order, stock reservation / release, split orders, coupon
| redemption only once the order succeeds, payment timeout.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
});

it('needs a signed-in customer', function (): void {
    $this->getJson(storeApi('checkout/options'), shopHeaders($this->shop->tenant))
        ->assertUnauthorized()
        ->assertJsonPath('data.reason', 'sign_in_required');

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe', 'idempotency_key' => 'guest-key-1'], shopHeaders($this->shop->tenant))
        ->assertUnauthorized();
});

it('returns addresses, the summary and the payment methods with nothing selected', function (): void {
    shopActingAs($this->customer);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], shopHeaders($this->shop->tenant))->assertCreated();

    $this->getJson(storeApi('checkout/options'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonCount(1, 'data.addresses')
        ->assertJsonPath('data.billing_same_as_shipping', true)
        ->assertJsonPath('data.cart.totals.grand_total.amount', 3000 + 500)
        ->assertJsonPath('data.payment_methods.0.code', 'stripe')
        ->assertJsonPath('data.payment_methods.0.source', 'central')
        ->assertJsonPath('data.payment_methods.0.payment_methods', ['card', 'apple_pay', 'google_pay', 'wallets'])
        ->assertJsonPath('data.selected_payment_method', null)
        ->assertJsonPath('data.can_pay', true);
});

it('blocks paying without an address, shipping or a gateway', function (): void {
    $customer = $this->shop->tenant->run(fn () => Customer::factory()->create());
    shopActingAs($customer);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], shopHeaders($this->shop->tenant))->assertCreated();

    $this->getJson(storeApi('checkout/options'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.can_pay', false)
        ->assertJsonPath('data.block', 'no_address')
        ->assertJsonPath('data.block_message', 'Please add a shipping address before placing your order.');

    $this->shop->tenant->run(fn () => CustomerAddress::factory()->default()->create(['customer_id' => $customer->id, 'country_id' => $this->shop->ae->id]));
    $this->getJson(storeApi('checkout/options'), shopHeaders($this->shop->tenant))->assertJsonPath('data.block', 'shipping_unavailable');

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'ae-order-1'])
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'shipping_unavailable');

    expect($this->shop->tenant->run(fn () => [Order::query()->count(), CheckoutAttempt::query()->count()]))->toBe([0, 0]);
});

it('filters gateways exactly like the old storefront (spec 08)', function (): void {
    shopGateway($this->shop->tenant, PaymentGatewayCode::Paypal);
    // Own keys: listed only when connected with every required key.
    PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Tap, 'is_active' => true])
        ->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => []]);

    $options = fn (): array => $this->shop->tenant->run(fn () => array_map(
        static fn ($option): string => $option->source->value.':'.$option->code->value,
        app(StorefrontGatewayService::class)->options(),
    ));

    expect($options())->toBe(['central:paypal', 'central:stripe']); // alphabetical

    $this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'paypal')->update(['is_hidden' => true]));
    expect($options())->toBe(['central:stripe']);

    $this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->firstOrFail()->update([
        'use_own' => true, 'credentials' => ['publishable_key' => 'pk_own'], 'connection_status' => GatewayConnection::Connected,
    ]));
    expect($options())->toBe([]); // own keys missing secret_key

    $this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->firstOrFail()->update([
        'credentials' => ['publishable_key' => 'pk_own', 'secret_key' => 'sk_own'],
    ]));
    expect($options())->toBe(['tenant:stripe']);

    // Centrally inactive → gone, whatever the store says.
    PaymentGateway::query()->where('code', 'stripe')->update(['is_active' => false]);
    expect($options())->toBe([]);

    // COD is never offered at storefront checkout.
    expect(app(StorefrontGatewayService::class)->supports(PaymentGatewayCode::Cod))->toBeFalse();
});

it('places an order idempotently and reserves its stock', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'same-key-123');

    expect($data['replayed'])->toBeFalse()
        ->and($data['grand_total'])->toBe(6380)
        ->and($data['orders'])->toHaveCount(1);

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'same-key-123'])
        ->assertOk()
        ->assertJsonPath('data.replayed', true)
        ->assertJsonPath('data.checkout_group', $data['checkout_group']);

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'paypal'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'same-key-123'])
        ->assertStatus(409);

    $order = $this->shop->tenant->run(fn () => Order::query()->with('items')->sole());

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->kind)->toBe(OrderKind::Catalog)
        ->and([$order->items_grand_total, $order->shipping_charge, $order->grand_total, $order->platform_profit, $order->vendor_profit])->toBe([5880, 500, 6380, 5420, 960])
        ->and($order->totals['lines'][0]['fixed_shipping'])->toBe(120)
        ->and($order->shipping_address['country_id'])->toBe($this->shop->sa->id)
        ->and($order->billing_address)->not->toBeNull()
        ->and($order->delivery_min_date)->not->toBeNull()
        ->and($order->items->sole()->quantity)->toBe(2)
        ->and($this->shop->tenant->run(fn () => Cart::query()->first()?->items()->count()))->toBe(0);

    $reservation = StockReservation::withoutTenantScope()->sole();
    expect($reservation->status)->toBe(StockReservationStatus::Reserved)
        ->and($reservation->quantity)->toBe(2)
        ->and($reservation->tenant_id)->toBe($this->shop->tenant->id);

    // Another store selling the same central product sees only 8 sellable units.
    $other = shopSetup('second');
    $other->tenant->run(fn () => $other->catalog->forceFill(['central_product_id' => $this->shop->central->id])->save());
    expect($other->tenant->run(fn () => app(StockReservationService::class)->sellable(StoreProductSource::Central, [$this->shop->central->id])))
        ->toMatchArray([$this->shop->central->id.':0' => 8]);

    expect(OrderFact::withoutTenantScope()->sole()->status)->toBe('pending_payment');
});

it('splits a mixed cart into a catalog and an own-products order', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2, $this->shop->own->id => 1], 'split-key-01');

    $orders = $this->shop->tenant->run(fn () => Order::query()->orderBy('id')->get());

    expect($data['orders'])->toHaveCount(2)
        ->and($orders->pluck('kind')->all())->toBe([OrderKind::Catalog, OrderKind::Own])
        ->and($orders->pluck('checkout_group')->unique())->toHaveCount(1)
        ->and($orders->pluck('grand_total')->all())->toBe([6380, 1500])
        ->and($orders[1]->shipping_charge)->toBe(0)
        ->and($orders[1]->vendor_profit)->toBe(1500)
        ->and(StockReservation::withoutTenantScope()->pluck('product_source')->map->value->sort()->values()->all())->toBe(['central', 'own']);
});

it('records the coupon redemption only once the order succeeds', function (): void {
    $this->shop->tenant->run(fn () => StoreCoupon::factory()->create(['code' => 'TENOFF', 'type' => 'percentage', 'percentage' => 10, 'amount_minor' => null, 'min_spend_minor' => 0]));
    shopActingAs($this->customer);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant))->assertCreated();
    $this->postJson(storeApi('cart/coupon'), ['code' => 'TENOFF'], shopHeaders($this->shop->tenant))->assertOk();

    $data = $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'coupon-key-1'])->assertCreated()->json('data');

    // 10 % of items 58.80 = 5.88
    expect($data['grand_total'])->toBe(6380 - 588)
        ->and($this->shop->tenant->run(fn () => StoreCouponRedemption::query()->count()))->toBe(0);

    $this->shop->tenant->run(fn () => app(OrderLifecycleService::class)->markGroupPaid($data['checkout_group'], 'stripe'));

    expect($this->shop->tenant->run(fn () => StoreCouponRedemption::query()->sole()->discount_minor))->toBe(588)
        ->and(StockReservation::withoutTenantScope()->sole()->status)->toBe(StockReservationStatus::Committed);

    // Paying again (webhook replay) changes nothing.
    $this->shop->tenant->run(fn () => app(OrderLifecycleService::class)->markGroupPaid($data['checkout_group'], 'stripe'));
    expect($this->shop->tenant->run(fn () => StoreCouponRedemption::query()->count()))->toBe(1);
});

it('releases every hold when a reservation fails part-way', function (): void {
    $this->shop->tenant->run(function (): void {
        expect(fn () => app(StockReservationService::class)->reserve('11111111-1111-1111-1111-111111111111', [
            new StockRequirement('22222222-2222-2222-2222-222222222222', StoreProductSource::Central, $this->shop->central->id, 0, 2),
            new StockRequirement('22222222-2222-2222-2222-222222222222', StoreProductSource::Own, $this->shop->own->id, 0, 6),
        ], now()->addMinutes(30)))->toThrow(CommerceException::class);
    });

    expect(StockReservation::withoutTenantScope()->pluck('status')->map->value->all())->toBe(['released']);
});

it('cancels unpaid orders after the payment timeout and frees their stock', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1], 'timeout-key-1');
    tenancy()->end();

    $this->travel(31)->minutes();
    (new ExpireUnpaidOrders)->handle(app(TenantRepositoryContract::class), app(StockReservationRepositoryContract::class));

    $order = $this->shop->tenant->run(fn () => Order::query()->where('uuid', $data['orders'][0]['uuid'])->sole());

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe(__('commerce::orders.notes.payment_timeout'))
        ->and(StockReservation::withoutTenantScope()->sole()->status)->toBe(StockReservationStatus::Released)
        ->and(OrderFact::withoutTenantScope()->sole()->status)->toBe('cancelled');
});

it('validates the place-order request', function (): void {
    shopActingAs($this->customer);

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'bitcoin'], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key', 'gateway']);

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'empty-cart-1'])
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'empty_cart');
});
