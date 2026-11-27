<?php

use App\Modules\Commerce\Models\Order;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Http\Middleware\SetLocale;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| RQA-SF-001: Store API calls made by a storefront page (session cookie, store domain)
| follow the storefront language - X-Locale, else the signed-in customer's saved
| language, else the `locale` cookie - before Accept-Language. Bearer / X-Tenant clients
| keep X-Locale / Accept-Language.
*/

beforeEach(function (): void {
    $this->shop = shopSetup('locale-shop');
});

it('answers a guest storefront call in the locale cookie language, not Accept-Language', function (): void {
    $url = $this->tenantUrl($this->shop->tenant, '/store-api/v1/cart/items');
    $arabic = $this->postJson($url, [], ['Accept-Language' => 'ar'])->assertUnprocessable()->json('errors.store_product_id.0');
    $english = $this->withCredentials()->withUnencryptedCookie(SetLocale::COOKIE, 'en')->postJson($url, [], ['Accept-Language' => 'ar'])
        ->assertUnprocessable()
        ->json('errors.store_product_id.0');

    expect(preg_match('/\p{Arabic}/u', $arabic))->toBe(1)
        ->and(preg_match('/\p{Arabic}/u', $english))->toBe(0);
});

it('follows the signed-in customer saved language and X-Locale still wins', function (): void {
    $customer = shopCustomer($this->shop);
    $this->shop->tenant->run(fn () => $customer->forceFill(['locale' => 'en'])->save());
    $this->actingAsCustomer($customer, $this->shop->tenant);

    $url = $this->tenantUrl($this->shop->tenant, '/store-api/v1/cart/items');
    $english = $this->postJson($url, [], ['Accept-Language' => 'ar'])->assertUnprocessable()->json('errors.store_product_id.0');
    $arabic = $this->postJson($url, [], ['Accept-Language' => 'en', 'X-Locale' => 'ar'])->assertUnprocessable()->json('errors.store_product_id.0');

    expect($english)->not->toBe($arabic)
        ->and(preg_match('/\p{Arabic}/u', $english))->toBe(0)
        ->and(preg_match('/\p{Arabic}/u', $arabic))->toBe(1);
});

it('places a storefront order in the customer saved language without X-Locale', function (): void {
    shopGateway($this->shop->tenant);
    $customer = shopCustomer($this->shop);
    $this->shop->tenant->run(fn () => $customer->forceFill(['locale' => 'en'])->save());
    $this->actingAsCustomer($customer, $this->shop->tenant);

    $this->postJson($this->tenantUrl($this->shop->tenant, '/store-api/v1/cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 1], ['Accept-Language' => 'ar'])->assertCreated();
    $uuid = $this->postJson($this->tenantUrl($this->shop->tenant, '/store-api/v1/checkout/place'), ['gateway' => PaymentGatewayCode::Stripe->value], ['Accept-Language' => 'ar', 'Idempotency-Key' => 'locale-order-0001'])
        ->assertCreated()
        ->json('data.orders.0.uuid');

    expect($this->shop->tenant->run(fn () => Order::query()->where('uuid', $uuid)->value('locale')))->toBe('en');
});

it('keeps X-Locale and Accept-Language for header (mobile) clients', function (): void {
    $arabic = $this->postJson(storeApi('cart/items'), [], shopHeaders($this->shop->tenant) + ['Accept-Language' => 'ar'])->json('errors.store_product_id.0');
    $english = $this->postJson(storeApi('cart/items'), [], shopHeaders($this->shop->tenant) + ['Accept-Language' => 'ar', 'X-Locale' => 'en'])->json('errors.store_product_id.0');

    expect(preg_match('/\p{Arabic}/u', $arabic))->toBe(1)
        ->and(preg_match('/\p{Arabic}/u', $english))->toBe(0);
});
