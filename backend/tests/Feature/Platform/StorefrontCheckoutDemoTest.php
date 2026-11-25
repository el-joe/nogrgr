<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Support\CheckoutDemoSamples;

/*
| Checkout address-step gallery (/admin/ui-kit/storefront-checkout): the real
| x-sf::checkout.address-step with a session address book, same permission as the
| component library.
*/

function checkoutDemoAdmin(bool $withPermission = true): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $withPermission ? $admin->assignRole('super-admin') : $admin->givePermissionTo('dashboard.view');

    return $admin;
}

it('requires system.ui-kit.view', function (): void {
    $this->get($this->centralUrl('/admin/ui-kit/storefront-checkout'))
        ->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(checkoutDemoAdmin(false), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-checkout'))
        ->assertForbidden();
});

it('renders the address step through every family bundle', function (string $family): void {
    $this->actingAs(checkoutDemoAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-checkout?family='.$family))
        ->assertOk()
        ->assertSee('data-theme-family="'.$family.'"', false)
        ->assertSee('data-checkout-address', false)
        ->assertSee('name="shipping_address_id" value="1"', false)
        ->assertSee('name="billing_same_as_shipping" value="1"', false)
        ->assertSee('data-address-modal-template', false)
        ->assertSee('data-pay-button', false)
        ->assertSee('Sara Alqahtani');
})->with(['ecommet', 'elora', 'souqify']);

it('starts empty and passes the shipping and gateway states to the step', function (): void {
    $this->actingAs(checkoutDemoAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-checkout?empty=1&shipping=not_sold&gateway=0&dir=rtl'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('"state":"not_sold"', false)
        ->assertSee('"gatewayAvailable":false', false)
        ->assertSee('name="shipping_address_id" value=""', false)
        ->assertSessionHas(CheckoutDemoSamples::SESSION_KEY, []);
});

it('adds, edits, sets default and deletes session addresses with the address JSON contract', function (): void {
    $admin = checkoutDemoAdmin();
    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/ui-kit/storefront-checkout'))->assertOk();

    $input = ['full_name' => 'Omar', 'phone' => '0501234567', 'phone_country' => 'sa', 'address_line_1' => 'Tahlia St 4', 'city' => 'Jeddah', 'country_id' => 1, 'is_default' => '0'];

    $this->postJson($this->centralUrl('/admin/ui-kit/storefront-checkout/addresses'), $input)
        ->assertOk()
        ->assertJsonPath('data.address.id', 3)
        ->assertJsonPath('data.address.phone', '+966501234567')
        ->assertJsonPath('data.address.one_line', 'Tahlia St 4, Jeddah, Saudi Arabia')
        ->assertJsonCount(3, 'data.addresses');

    $this->putJson($this->centralUrl('/admin/ui-kit/storefront-checkout/addresses/3'), ['city' => 'Mecca'] + $input)
        ->assertOk()
        ->assertJsonPath('data.address.city', 'Mecca');

    $this->postJson($this->centralUrl('/admin/ui-kit/storefront-checkout/addresses/3/default'))
        ->assertOk()
        ->assertJsonPath('data.addresses.0.id', 3)
        ->assertJsonPath('data.addresses.0.is_default', true);

    $this->deleteJson($this->centralUrl('/admin/ui-kit/storefront-checkout/addresses/3'))
        ->assertOk()
        ->assertJsonCount(2, 'data.addresses')
        ->assertJsonPath('data.addresses.0.is_default', true);
});

it('validates the canonical address fields', function (): void {
    $this->actingAs(checkoutDemoAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/storefront-checkout/addresses'), ['phone' => '123', 'phone_country' => 'SA', 'country_id' => 99])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'phone', 'address_line_1', 'city', 'country_id']);
});

it('suggests cities for the chosen country', function (): void {
    $this->actingAs(checkoutDemoAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/ui-kit/storefront-checkout/countries/1/cities?q=jed'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Jeddah')
        ->assertJsonCount(1, 'data');
});

it('echoes the hidden checkout inputs on pay', function (): void {
    $this->actingAs(checkoutDemoAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/storefront-checkout/pay'), ['shipping_address_id' => 1, 'billing_address_id' => 2, 'billing_same_as_shipping' => 0])
        ->assertOk()
        ->assertJsonPath('message', __('platform::checkout-demo.paid', ['shipping' => 1, 'billing' => 2]));
});
