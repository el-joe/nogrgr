<?php

use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\Support\StorefrontTheme;

/*
| Storefront auth + account pages rendered through the x-sf::* shells of every theme
| family. The family comes from the tenant's data (`theme_family`, see
| StorefrontTheme::family()); a store without one, or with an unknown value, uses ecommet.
*/

dataset('families', StorefrontTheme::FAMILIES);

it('renders the auth pages with the store family shell', function (string $family, string $path, string $hook): void {
    $tenant = $this->createTenant('acme', ['name' => 'Acme Goods', 'theme_family' => $family]);

    $this->get($this->tenantUrl($tenant, $path))
        ->assertOk()
        ->assertSee('data-theme-family="'.$family.'"', false)
        ->assertSee($hook, false)
        ->assertSee('Acme Goods')
        ->assertDontSee('ELORA');
})->with('families')->with([
    'login' => ['/account/login', 'data-login-form'],
    'register' => ['/account/register', 'data-phone-input'],
    'forgot password' => ['/account/forgot-password', 'data-sent-state'],
    'reset password' => ['/account/reset-password/token-123?email=sara%40example.test', 'data-expired-state'],
]);

it('renders the account pages with the account sidebar for every family', function (string $family, string $path, string $hook): void {
    Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('acme', ['theme_family' => $family]);
    $customer = $this->createCustomer($tenant, ['name' => 'Sara Ali', 'email' => 'sara@example.test']);

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, $path))
        ->assertOk()
        ->assertSee('data-theme-family="'.$family.'"', false)
        ->assertSee($hook, false)
        ->assertSee('/account/addresses"', false)
        ->assertSee('/account/logout"', false);
})->with('families')->with([
    'profile' => ['/account/profile', 'data-delete-account-form'],
    'notifications' => ['/account/notifications', 'role="switch"'],
    'addresses' => ['/account/addresses', 'data-address-book data-mode="manage"'],
]);

it('falls back to the ecommet family', function (array $attributes): void {
    $tenant = $this->createTenant('acme', $attributes);

    $this->get($this->tenantUrl($tenant, '/account/login'))
        ->assertOk()
        ->assertSee('data-theme-family="ecommet"', false);
})->with([
    'no family' => [[]],
    'unknown family' => [['theme_family' => 'unknown']],
]);

it('hides account links whose pages do not exist yet', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant);

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/profile'))
        ->assertOk()
        ->assertSee('/account/profile"', false)
        ->assertDontSee('/account/orders', false)
        ->assertDontSee('/account/returns', false)
        ->assertDontSee('/account/wishlist', false);
});

it('renders the saved addresses as cards with the edit, default and delete hooks', function (): void {
    $saudi = Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('acme', ['theme_family' => 'souqify']);
    $customer = $this->createCustomer($tenant);
    $this->initializeTenancy($tenant);
    $address = CustomerAddress::factory()->for($customer)->create(['country_id' => $saudi->id, 'full_name' => 'Sara Home', 'is_default' => true]);
    $this->endTenancy();

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/addresses'))
        ->assertOk()
        ->assertSee('data-address-card="'.$address->id.'"', false)
        ->assertSee('data-address-edit="'.$address->id.'"', false)
        ->assertSee('data-address-delete="'.$address->id.'"', false)
        ->assertSee('Sara Home')
        ->assertSee('data-address-template', false)
        ->assertSee('data-address-delete-modal', false);
});

it('renders the account pages right-to-left in Arabic with Latin digits', function (string $family): void {
    $tenant = $this->createTenant('acme', ['theme_family' => $family]);
    $customer = $this->createCustomer($tenant);

    $this->actingAsCustomer($customer, $tenant)
        ->withUnencryptedCookie('locale', 'ar')
        ->get($this->tenantUrl($tenant, '/account/profile'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('identity::account.profile.title', [], 'ar'))
        ->assertDontSee('٠', false);
})->with('families');
