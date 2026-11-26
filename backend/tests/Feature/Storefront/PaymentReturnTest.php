<?php

use App\Modules\Commerce\Models\Order;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Gateway return `checkout/payment/{gateway}/{order}?status=…&reference=…` (sf-checkout,
| SF-ECO-023b): confirmed through Commerce PaymentService, then the order page with a toast,
| or "Payment was not completed".
*/

function paypalReturnShop(): object
{
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'PP-1', 'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']]]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PP-1/capture' => Http::response([
            'id' => 'PP-1', 'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['value' => '63.80', 'currency_code' => 'USD']]]]]],
        ]),
    ]);
    $shop = shopSetup();
    shopGateway($shop->tenant, PaymentGatewayCode::Paypal);
    $shop->customer = shopCustomer($shop);
    $shop->placed = shopPlaceOrder($shop, $shop->customer, [$shop->catalog->id => 2], gateway: PaymentGatewayCode::Paypal);
    $shop->reference = test()->postJson(storeApi('payments/'.$shop->placed['payment_order'].'/start'), ['gateway' => 'paypal'], shopHeaders($shop->tenant))
        ->assertCreated()
        ->json('data.payment.reference');

    return $shop;
}

it('confirms a successful return and lands on the order page with the success toast', function (): void {
    $shop = paypalReturnShop();
    $uuid = $shop->placed['payment_order'];

    $response = $this->actingAsCustomer($shop->customer, $shop->tenant)
        ->get($this->tenantUrl($shop->tenant, '/checkout/payment/paypal/'.$uuid.'?status=success&reference='.$shop->reference))
        ->assertRedirect()
        ->assertSessionHas('success', 'Payment completed successfully.');

    expect($response->headers->get('Location'))->toContain($uuid);
    expect($shop->tenant->run(fn () => Order::query()->where('uuid', $uuid)->value('status')->value))->toBe('paid');
});

it('lands a cancelled return on the order page with the cancelled toast', function (): void {
    $shop = paypalReturnShop();

    $this->actingAsCustomer($shop->customer, $shop->tenant)
        ->get($this->tenantUrl($shop->tenant, '/checkout/payment/paypal/'.$shop->placed['payment_order'].'?status=cancel&reference='.$shop->reference))
        ->assertRedirect()
        ->assertSessionHas('info', 'Payment was cancelled.');
});

it('shows "Payment was not completed" with retry for a failed return', function (): void {
    $shop = paypalReturnShop();
    $uuid = $shop->placed['payment_order'];

    $this->actingAsCustomer($shop->customer, $shop->tenant)
        ->get($this->tenantUrl($shop->tenant, '/checkout/payment/paypal/'.$uuid.'?status=success&reference=pay_unknown'))
        ->assertOk()
        ->assertSee('Payment was not completed')
        ->assertSee('/checkout/pay/'.$uuid, false);
});

it('asks a signed-out payer to sign in first', function (): void {
    $shop = paypalReturnShop();

    $this->get($this->tenantUrl($shop->tenant, '/checkout/payment/paypal/'.$shop->placed['payment_order'].'?status=success&reference='.$shop->reference))
        ->assertRedirectContains('/account/login');
});
