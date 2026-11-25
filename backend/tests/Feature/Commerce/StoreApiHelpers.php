<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Shared\Money\Money;
use Laravel\Sanctum\Sanctum;

/*
| Shared setup of the Store API tests (P5 batch 2): a store selling in SA + AE with a 20 %
| default markup; SA ships ($5 up to 1 kg, free from 1001 g), AE has no shipping rule; a
| central product (24.00 USD, 300 g, fixed shipping SA 1.20) and an own product (15.00).
|
| Golden cart (2 × central in SA): unit 24.00 + 4.80 profit, + 1.20 fixed once per line
|   items 58.80, shipping 5.00, grand 63.80, platform 54.20, vendor 9.60.
*/

if (! function_exists('shopSetup')) {
    /**
     * @return object{tenant: Tenant, sa: Country, ae: Country, central: Product, catalog: StoreProduct, own: StoreProduct}
     */
    function shopSetup(string $slug = 'shop'): object
    {
        $sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
        $ae = Country::query()->where('iso2', 'AE')->first() ?? Country::factory()->iso('AE')->create();
        $tenant = test()->createTenant($slug, ['selling_country_ids' => [$sa->id, $ae->id], 'default_country_id' => $sa->id, 'profit_percent_bps' => 2000]);

        if (ShippingRule::query()->whereHas('countries', static fn ($query) => $query->whereKey($sa->id))->doesntExist()) {
            $rule = ShippingRule::factory()->create();
            $rule->countries()->attach($sa->id);
            $zone = ShippingZone::query()->create(['shipping_rule_id' => $rule->id, 'name' => 'All KSA']);
            $zone->bands()->create(['name' => 'Standard', 'min_weight_grams' => 0, 'max_weight_grams' => 1000, 'price' => Money::of(5)]);
            $zone->bands()->create(['name' => 'Free', 'min_weight_grams' => 1001, 'max_weight_grams' => null, 'price' => Money::zero()]);
        }

        $central = Product::factory()->create([
            'base_price' => Money::of('24.00'),
            'weight_grams' => 300,
            'stock' => 10,
            'fixed_shipping_costs' => [(string) $sa->id => 120, (string) $ae->id => 0],
        ]);

        [$catalog, $own] = $tenant->run(fn (): array => [
            StoreProduct::factory()->central($central->id)->create(),
            StoreProduct::factory()->create(['price' => Money::of('15.00'), 'stock' => 5]),
        ]);

        return (object) compact('tenant', 'sa', 'ae', 'central', 'catalog', 'own');
    }

    /**
     * A customer of the store with a default SA address.
     */
    function shopCustomer(object $shop, ?int $countryId = null): Customer
    {
        return $shop->tenant->run(function () use ($shop, $countryId): Customer {
            $customer = Customer::factory()->create();
            CustomerAddress::factory()->default()->create(['customer_id' => $customer->id, 'country_id' => $countryId ?? $shop->sa->id]);

            return $customer->fresh() ?? $customer;
        });
    }

    /**
     * Central Stripe / PayPal usable for storefront orders (vendor-payments config,
     * connected) + the store row using the central credentials.
     *
     * @param  array<string, mixed>  $store
     */
    function shopGateway(Tenant $tenant, PaymentGatewayCode $code = PaymentGatewayCode::Stripe, array $store = []): PaymentGateway
    {
        $gateway = PaymentGateway::query()->where('code', $code->value)->first()
            ?? PaymentGateway::factory()->create(['code' => $code, 'is_active' => true]);

        if ($gateway->configs()->where('purpose', GatewayPurpose::VendorPayments->value)->doesntExist()) {
            $gateway->configs()->create([
                'purpose' => GatewayPurpose::VendorPayments,
                'is_enabled' => true,
                'mode' => GatewayMode::Test,
                'credentials' => match ($code) {
                    PaymentGatewayCode::Paypal => ['client_id' => 'pp-client', 'client_secret' => 'pp-secret', 'webhook_id' => 'WH-1'],
                    default => ['publishable_key' => 'pk_central', 'secret_key' => 'sk_central', 'webhook_secret' => 'whsec_central'],
                },
                'last_tested_at' => now(),
                'last_error' => null,
            ]);
        }

        $tenant->run(static fn () => StorePaymentGateway::query()->updateOrCreate(['code' => $code->value], array_merge([
            'is_active' => true,
            'is_hidden' => false,
            'use_own' => false,
            'mode' => GatewayMode::Test,
            'connection_status' => GatewayConnection::NotConnected,
        ], $store)));

        return $gateway->load('configs');
    }

    /**
     * Headers of a Store API call for the store (mobile style: X-Tenant).
     *
     * @return array<string, string>
     */
    function shopHeaders(Tenant $tenant, ?string $cartToken = null): array
    {
        return array_filter(['X-Tenant' => (string) $tenant->id, 'Accept' => 'application/json', 'X-Cart-Token' => $cartToken]);
    }

    function shopActingAs(Customer $customer): void
    {
        Sanctum::actingAs($customer, ['*'], 'sanctum');
    }

    function storeApi(string $path): string
    {
        return '/store-api/v1/'.ltrim($path, '/');
    }

    /**
     * Stripe webhook signature header for a payload.
     */
    function shopStripeSignature(string $payload, string $secret): string
    {
        $time = time();

        return 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret);
    }
}

if (! function_exists('shopPlaceOrder')) {
    /**
     * Signed-in customer fills the cart and places an order with Stripe; returns the
     * place-order response data.
     *
     * @param  array<int, int>  $lines  store_product_id => quantity
     * @return array<string, mixed>
     */
    function shopPlaceOrder(object $shop, Customer $customer, array $lines, string $key = 'order-key-0001', PaymentGatewayCode $gateway = PaymentGatewayCode::Stripe): array
    {
        shopActingAs($customer);

        foreach ($lines as $productId => $quantity) {
            test()->postJson(storeApi('cart/items'), ['store_product_id' => $productId, 'quantity' => $quantity], shopHeaders($shop->tenant))->assertCreated();
        }

        return test()->postJson(storeApi('checkout/place'), ['gateway' => $gateway->value], shopHeaders($shop->tenant) + ['Idempotency-Key' => $key])
            ->assertCreated()
            ->json('data');
    }
}
