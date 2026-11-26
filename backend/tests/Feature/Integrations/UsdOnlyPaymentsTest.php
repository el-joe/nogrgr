<?php

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Integrations\DTOs\CheckoutRequestData;
use App\Modules\Integrations\DTOs\RefundRequestData;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Language;
use App\Shared\Money\Money;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
| Decision #87 (client 2026-11-26): USD is the base and ONLY payment currency. Every
| driver call (checkout / PaymentIntent and refund) is built from USD Money; anything
| else is refused before a gateway is called. Language purchases (VEN-007) charge USD
| through the inline Stripe card as well.
*/

it('refuses a checkout or refund in another currency than USD', function (string $currency): void {
    expect(fn () => new CheckoutRequestData(Money::ofMinor(1000, $currency), 'ref', 'x', 'https://a.test/s', 'https://a.test/c'))
        ->toThrow(PaymentGatewayException::class, 'USD only')
        ->and(fn () => new RefundRequestData('pi_1', Money::ofMinor(1000, $currency), 'RF-1'))
        ->toThrow(PaymentGatewayException::class, 'USD only');
})->with(['SAR', 'EGP', 'EUR']);

it('accepts USD amounts', function (): void {
    expect((new CheckoutRequestData(Money::ofMinor(1000), 'ref', 'x', 'https://a.test/s', 'https://a.test/c'))->amount->currency)->toBe('USD')
        ->and((new RefundRequestData('pi_1', Money::ofMinor(500), 'RF-1'))->amount->currency)->toBe('USD');
});

it('buys a language with the inline Stripe card, charged in USD', function (): void {
    Notification::fake();
    $sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('usd-lang', ['selling_country_ids' => [$sa->id]]);
    $owner = $this->createVendorOwner($tenant);
    $french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $french->update(['is_active' => true, 'price_minor' => 4900, 'country_ids' => null]);
    $gateway = PaymentGateway::factory()->create(['code' => PaymentGatewayCode::Stripe, 'is_active' => true, 'sort_order' => 0, 'fee_percent_bps' => 0, 'fee_fixed_minor' => 0]);
    $gateway->configs()->create(['purpose' => GatewayPurpose::VendorPayments, 'is_enabled' => true, 'mode' => GatewayMode::Test, 'credentials' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_test_secret']]);
    $intent = ['id' => 'pi_lang_1', 'object' => 'payment_intent', 'client_secret' => 'pi_lang_1_secret', 'amount' => 4900, 'currency' => 'usd'];
    Http::fake([
        'https://api.stripe.com/v1/payment_intents' => Http::response($intent + ['status' => 'requires_payment_method']),
        'https://api.stripe.com/v1/payment_intents/pi_lang_1' => Http::response($intent + ['status' => 'succeeded', 'amount_received' => 4900]),
    ]);
    $url = fn (string $path = '') => $this->tenantUrl($tenant, 'admin/onboarding/setup'.$path);

    $this->actingAsVendor($owner, $tenant)->get($url('?step=languages'))->assertOk()->assertSee('data-stripe-card', false);
    $return = (string) $this->actingAsVendor($owner, $tenant)->postJson($url('/languages/'.$french->id.'/purchase'), ['gateway' => 'stripe'])
        ->assertOk()
        ->assertJsonPath('data.inline.client_secret', 'pi_lang_1_secret')
        ->json('data.inline.return_url');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.com/v1/payment_intents' && $request['currency'] === 'usd' && (int) $request['amount'] === 4900);

    $this->actingAsVendor($owner, $tenant)->get($return)->assertRedirect($url('?step=languages'));

    expect(PaymentLog::query()->withoutGlobalScopes()->sole()->status)->toBe(PaymentLogStatus::Captured);
});
