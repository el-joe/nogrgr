<?php

use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Storefront\Services\Catalog\StorefrontShell;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| RQA-SF-002: the "We accept" / "Payment methods" badges equal what checkout offers
| (StorefrontGatewayService::options()) - active store rows without a usable central
| gateway (COD, Paymob without keys) show no badge.
*/

it('shows only the badges of the payment methods checkout offers', function (): void {
    $shop = shopSetup('badges-shop');
    shopGateway($shop->tenant);

    foreach ([PaymentGatewayCode::Cod, PaymentGatewayCode::Paymob] as $code) {
        PaymentGateway::query()->where('code', $code->value)->first() ?? PaymentGateway::factory()->create(['code' => $code, 'is_active' => true]);
        $shop->tenant->run(static fn () => StorePaymentGateway::query()->updateOrCreate(['code' => $code->value], [
            'is_active' => true,
            'is_hidden' => false,
            'use_own' => false,
            'mode' => GatewayMode::Test,
            'connection_status' => GatewayConnection::NotConnected,
        ]));
    }

    expect($shop->tenant->run(fn (): array => app(StorefrontShell::class)->paymentBadges()))->toBe(['visa', 'mastercard']);
});

it('renders no cash on delivery badge in the storefront footer', function (): void {
    $shop = shopSetup('badges-footer');
    shopGateway($shop->tenant);
    $shop->tenant->run(static fn () => StorePaymentGateway::query()->updateOrCreate(['code' => PaymentGatewayCode::Cod->value], [
        'is_active' => true,
        'is_hidden' => false,
        'use_own' => false,
        'mode' => GatewayMode::Test,
        'connection_status' => GatewayConnection::NotConnected,
    ]));

    $this->get($this->tenantUrl($shop->tenant, '/?lang=en'))
        ->assertOk()
        ->assertSee('title="Visa"', false)
        ->assertDontSee('title="Cash on delivery"', false);
});

it('shows no badges when checkout offers nothing', function (): void {
    $shop = shopSetup('badges-none');
    $shop->tenant->run(static fn () => StorePaymentGateway::query()->updateOrCreate(['code' => PaymentGatewayCode::Cod->value], [
        'is_active' => true,
        'is_hidden' => false,
        'use_own' => false,
        'mode' => GatewayMode::Test,
        'connection_status' => GatewayConnection::NotConnected,
    ]));

    expect($shop->tenant->run(fn (): array => app(StorefrontShell::class)->paymentBadges()))->toBe([]);
});
