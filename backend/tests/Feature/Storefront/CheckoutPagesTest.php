<?php

use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Integrations\Enums\PaymentGatewayCode;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Checkout page, its fragment and the pay page (sf-checkout, SF-ECO-023 / 023b).
*/

function checkoutShop(): object
{
    $shop = shopSetup();
    shopGateway($shop->tenant);
    $shop->customer = shopCustomer($shop);

    return $shop;
}

it('sends guests to the account login with a redirect back to checkout', function (): void {
    $shop = shopSetup();

    $this->get($this->tenantUrl($shop->tenant, '/checkout'))
        ->assertRedirectContains('/account/login')
        ->assertRedirectContains('redirect=%2Fcheckout');
});

it('sends a signed-in customer with an empty cart back to the cart', function (): void {
    $shop = checkoutShop();

    $this->actingAsCustomer($shop->customer, $shop->tenant)
        ->get($this->tenantUrl($shop->tenant, '/checkout'))
        ->assertRedirectContains('/cart');
});

it('renders the checkout with addresses, summary, payment methods and the pay total', function (): void {
    $shop = checkoutShop();
    $this->actingAsCustomer($shop->customer, $shop->tenant);
    $this->postJson($this->tenantUrl($shop->tenant, '/store-api/v1/cart/items'), ['store_product_id' => $shop->catalog->id, 'quantity' => 2])->assertCreated();

    $html = $this->get($this->tenantUrl($shop->tenant, '/checkout'))
        ->assertOk()
        ->assertSee('data-checkout-address', false)
        ->assertSee('data-checkout-summary', false)
        ->assertSee('Payment details')
        ->assertSee('name="payment_choice"', false)
        ->assertSee('Pay $63.80')
        ->getContent();

    // Nothing pre-selected (spec 08), inline card panel hidden until Stripe is chosen.
    expect($html)->not->toContain('data-gateway-radio checked')
        ->and($html)->toContain('id="inline-card-form"');
});

it('re-prices the summary for the chosen address through the fragment', function (): void {
    $shop = checkoutShop();
    $this->actingAsCustomer($shop->customer, $shop->tenant);
    $this->postJson($this->tenantUrl($shop->tenant, '/store-api/v1/cart/items'), ['store_product_id' => $shop->catalog->id, 'quantity' => 2])->assertCreated();
    $aeAddress = $shop->tenant->run(fn () => CustomerAddress::factory()->create(['customer_id' => $shop->customer->id, 'country_id' => $shop->ae->id])->id);

    $this->getJson($this->tenantUrl($shop->tenant, '/checkout/fragment'))
        ->assertOk()
        ->assertJsonPath('data.shipping.state', 'ok')
        ->assertJsonPath('data.block', null)
        ->assertJsonPath('data.pay_total', '$63.80');

    // AE has no shipping rule: the info box says shipping is unavailable and paying is blocked.
    $this->getJson($this->tenantUrl($shop->tenant, '/checkout/fragment?shipping_address_id='.$aeAddress))
        ->assertOk()
        ->assertJsonPath('data.shipping.state', 'unavailable')
        ->assertJsonPath('data.block', 'shipping_unavailable');
});

it('shows the no payment method notice when the store has no gateway', function (): void {
    $shop = shopSetup();
    $customer = shopCustomer($shop);
    $this->actingAsCustomer($customer, $shop->tenant);
    $this->postJson($this->tenantUrl($shop->tenant, '/store-api/v1/cart/items'), ['store_product_id' => $shop->own->id, 'quantity' => 1])->assertCreated();

    $this->get($this->tenantUrl($shop->tenant, '/checkout'))
        ->assertOk()
        ->assertSee('No payment method is available right now. Please try again later.');
});

it('opens the pay page of a placed order and the redirect page for a redirect gateway', function (): void {
    $shop = checkoutShop();
    shopGateway($shop->tenant, PaymentGatewayCode::Paypal);
    $placed = shopPlaceOrder($shop, $shop->customer, [$shop->catalog->id => 1]);

    $this->actingAsCustomer($shop->customer, $shop->tenant)
        ->get($this->tenantUrl($shop->tenant, '/checkout/pay/'.$placed['payment_order']))
        ->assertOk()
        ->assertSee('data-payment-page', false)
        ->assertSee('Payment details');

    $this->get($this->tenantUrl($shop->tenant, '/checkout/pay/'.$placed['payment_order'].'?gateway=paypal'))
        ->assertOk()
        ->assertSee('data-payment-redirect', false)
        ->assertSee('Redirecting to secure payment...');
});

it('hides another customer\'s order behind a 404', function (): void {
    $shop = checkoutShop();
    $placed = shopPlaceOrder($shop, $shop->customer, [$shop->catalog->id => 1]);
    $other = shopCustomer($shop);

    $this->actingAsCustomer($other, $shop->tenant)
        ->get($this->tenantUrl($shop->tenant, '/checkout/pay/'.$placed['payment_order']))
        ->assertNotFound();
});
