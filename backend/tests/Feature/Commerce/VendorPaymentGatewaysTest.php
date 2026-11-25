<?php

use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| VEN-078 / 078a store payment gateways: list of offered gateways, own keys stored
| encrypted and shown masked, sandbox / live, connection check, primary gateway. The
| sidebar link vendor.settings.payment-gateways.index resolves.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    shopGateway($this->shop->tenant, PaymentGatewayCode::Paypal);
    $this->owner = $this->createVendorOwner($this->shop->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->shop->tenant, 'admin/settings/payment-gateways'.$path);
});

it('guards the pages by permission', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->shop->tenant, 'login'));

    $viewer = $this->shop->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'settings.payment-gateways.view']);

        return $staff;
    });
    $this->actingAsVendor($viewer, $this->shop->tenant);

    $this->get(($this->url)())->assertOk()->assertDontSee(__('commerce::gateways.save'));
    $this->putJson(($this->url)('/stripe'), ['is_active' => 1, 'use_own' => 0, 'mode' => 'test'])->assertForbidden();
    $this->postJson(($this->url)('/stripe/test'))->assertForbidden();
    $this->postJson(($this->url)('/stripe/primary'))->assertForbidden();
});

it('lists the offered gateways with their state and readiness', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);

    $this->get(($this->url)())
        ->assertOk()
        ->assertSee('Stripe')
        ->assertSee('PayPal')
        ->assertSee(__('commerce::gateways.readiness.title'))
        ->assertSee(__('commerce::gateways.states.testing'));
    $this->get(($this->url)('/paypal'))->assertOk()->assertSee(__('integrations::admin.gateways.credentials.client_id'));
    $this->getJson(($this->url)('/tabby'))->assertNotFound();
    expect(Route::has('vendor.settings.payment-gateways.index'))->toBeTrue();
});

it('stores own keys encrypted, shows them masked and keeps a blank secret', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);

    $this->putJson(($this->url)('/stripe'), ['is_active' => 1, 'use_own' => 1, 'mode' => 'test', 'credentials' => ['publishable_key' => 'pk_test_own']])->assertUnprocessable();
    $this->putJson(($this->url)('/stripe'), ['is_active' => 1, 'use_own' => 1, 'mode' => 'lunar'])->assertJsonValidationErrors('mode');
    $this->putJson(($this->url)('/stripe'), ['is_active' => 1, 'use_own' => 1, 'mode' => 'test', 'credentials' => ['publishable_key' => 'pk_test_own', 'secret_key' => 'sk_test_supersecret7QaZ']])->assertOk();

    [$raw, $row] = $this->shop->tenant->run(fn (): array => [
        DB::table('store_payment_gateways')->where('code', 'stripe')->value('credentials'),
        StorePaymentGateway::query()->where('code', 'stripe')->sole(),
    ]);

    expect($raw)->not->toContain('sk_test_supersecret7QaZ')
        ->and($row->credentials['secret_key'])->toBe('sk_test_supersecret7QaZ')
        ->and($row->connection_status)->toBe(GatewayConnection::NotConnected);

    $page = $this->get(($this->url)('/stripe'))->assertOk();
    $page->assertDontSee('sk_test_supersecret7QaZ')->assertSee('7QaZ');

    $this->putJson(($this->url)('/stripe'), ['is_active' => 1, 'use_own' => 1, 'mode' => 'test', 'credentials' => ['publishable_key' => 'pk_test_own', 'secret_key' => '']])->assertOk();
    expect($this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->sole()->credentials['secret_key']))->toBe('sk_test_supersecret7QaZ');
});

it('checks the connection of own keys and sets the primary gateway', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $this->postJson(($this->url)('/stripe/test'))->assertUnprocessable();
    $this->putJson(($this->url)('/stripe'), ['is_active' => 1, 'use_own' => 1, 'mode' => 'test', 'credentials' => ['publishable_key' => 'pk_test_own', 'secret_key' => 'sk_test_supersecret7QaZ']])->assertOk();

    Http::fake(['https://api.stripe.com/v1/balance' => Http::sequence()->push(['error' => ['message' => 'Invalid API Key']], 401)->push(['object' => 'balance'])]);
    $this->postJson(($this->url)('/stripe/test'))->assertUnprocessable()->assertJsonPath('data.ok', false);
    expect($this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->sole()->last_error))->not->toBeNull();
    $this->postJson(($this->url)('/stripe/primary'))->assertUnprocessable();

    $this->postJson(($this->url)('/stripe/test'))->assertOk()->assertJsonPath('data.ok', true);
    $this->postJson(($this->url)('/stripe/primary'))->assertOk();
    $this->postJson(($this->url)('/paypal/primary'))->assertOk();

    $rows = $this->shop->tenant->run(fn () => StorePaymentGateway::query()->pluck('is_primary', 'code')->all());

    expect($rows['stripe'])->toBeFalse()->and($rows['paypal'])->toBeTrue()
        ->and($this->shop->tenant->run(fn () => StorePaymentGateway::query()->where('code', 'stripe')->sole()->connection_status))->toBe(GatewayConnection::Connected);
});
