<?php

use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Services\Testing\GatewayConnectionTester;
use App\Modules\Localization\Models\Currency;
use App\Shared\Money\Money;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/IntegrationsHelpers.php';

/*
| Admin › Integrations › Payment gateways (ADM-100a/b): catalogue, active switch,
| per-purpose configs (encrypted credentials), fees in basis points / USD minor
| units, connection tests (Http::fake) with typed errors.
*/

function stripeGatewayPayload(array $vendor = [], array $tenant = []): array
{
    return [
        'fee_percent' => '2.90',
        'fee_fixed' => '0.30',
        'configs' => [
            'vendor_payments' => array_replace_recursive([
                'enabled' => '1',
                'mode' => 'live',
                'credentials' => ['publishable_key' => 'pk_live_51NabcdefQe', 'secret_key' => 'sk_live_51NSECRETVALUE4f9K', 'webhook_secret' => 'whsec_abcdefgh9Lx1'],
                'currencies' => ['USD', 'SAR'],
            ], $vendor),
            'tenant_registration' => array_replace_recursive([
                'enabled' => '0',
                'mode' => 'test',
                'credentials' => ['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => ''],
                'currencies' => [],
            ], $tenant),
        ],
    ];
}

function gatewayCurrencies(): void
{
    Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    Currency::factory()->create(['code' => 'SAR', 'is_active' => true]);
    Currency::factory()->create(['code' => 'JPY', 'is_active' => false]);
}

dataset('gateway endpoints', [
    'index' => ['get', '/admin/integrations/gateways', 'integrations.view'],
    'edit' => ['get', '/admin/integrations/gateways/stripe/edit', 'integrations.view'],
    'update' => ['put', '/admin/integrations/gateways/stripe', 'integrations.manage'],
    'toggle' => ['patch', '/admin/integrations/gateways/stripe/active', 'integrations.manage'],
    'test' => ['post', '/admin/integrations/gateways/stripe/test/vendor_payments', 'integrations.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $this->{$method}($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
})->with('gateway endpoints');

it('forbids admins without the integrations permission', function (string $method, string $path, string $permission): void {
    $admin = $permission === 'integrations.manage' ? integrationsAdmin('integrations.view') : integrationsAdmin();

    $this->actingAs($admin, 'admin')->{$method.'Json'}($this->centralUrl($path))->assertForbidden();
})->with('gateway endpoints');

it('lists every launch gateway, inactive by default', function (): void {
    $this->actingAs(integrationsAdmin('integrations.view'), 'admin')
        ->get($this->centralUrl('/admin/integrations/gateways'))
        ->assertOk()
        ->assertSee('MyFatoorah')
        ->assertSee('Tamara')
        ->assertSee(route('admin.integrations.gateways.edit', 'stripe'), false);

    expect(PaymentGateway::query()->count())->toBe(count(PaymentGatewayCode::cases()))
        ->and(PaymentGateway::query()->where('is_active', true)->exists())->toBeFalse();
});

it('renders each gateway tile with its brand colour token (QA-P2-007)', function (): void {
    $response = $this->actingAs(integrationsAdmin('integrations.view'), 'admin')
        ->get($this->centralUrl('/admin/integrations/gateways'))
        ->assertOk();

    $css = (string) file_get_contents(resource_path('css/shared/gateway-tiles.css'));

    foreach (PaymentGatewayCode::cases() as $code) {
        $response->assertSee('class="ui-gateway-tile grid size-8 shrink-0 place-items-center rounded-lg text-xs font-medium" data-gateway="'.$code->value.'"', false);
        expect($css)->toContain('--gateway-'.$code->value.':')
            ->toContain(".ui-gateway-tile[data-gateway='".$code->value."']");
    }
});

it('returns 404 for an unknown gateway', function (): void {
    $this->actingAs(integrationsAdmin('integrations.manage'), 'admin')
        ->get($this->centralUrl('/admin/integrations/gateways/bitcoin/edit'))
        ->assertNotFound();
});

it('saves both configs with encrypted credentials and fees', function (): void {
    gatewayCurrencies();
    $admin = integrationsAdmin('integrations.manage');

    $response = $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), stripeGatewayPayload())
        ->assertOk();
    expect($response->getContent())->not->toContain('sk_live_51NSECRETVALUE4f9K');

    $gateway = PaymentGateway::query()->where('code', 'stripe')->with('configs')->sole();
    $vendor = $gateway->config(GatewayPurpose::VendorPayments);

    expect($gateway->fee_percent_bps)->toBe(290)
        ->and($gateway->fee_fixed_minor)->toBe(30)
        ->and($gateway->feeFor(Money::of(100))->minor)->toBe(320)
        ->and($vendor->is_enabled)->toBeTrue()
        ->and($vendor->mode)->toBe(GatewayMode::Live)
        // USD is the only payment currency (decision #87): no settlement currencies are kept.
        ->and($vendor->currencies)->toBeEmpty()
        ->and($vendor->credential('secret_key'))->toBe('sk_live_51NSECRETVALUE4f9K')
        ->and($vendor->getRawOriginal('credentials'))->not->toContain('SECRETVALUE')
        ->and($vendor->toArray())->not->toHaveKey('credentials')
        ->and($gateway->config(GatewayPurpose::TenantRegistration)->is_enabled)->toBeFalse();

    $page = $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/integrations/gateways/stripe/edit'))->assertOk();
    expect($page->getContent())->not->toContain('sk_live_51NSECRETVALUE4f9K')->toContain('4f9K')->toContain('pk_live_51NabcdefQe')
        ->not->toContain('[currencies]');

    // Blank secret keeps the stored one.
    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), stripeGatewayPayload(['credentials' => ['secret_key' => '']]))
        ->assertOk();
    expect(PaymentGatewayConfig::query()->where('purpose', 'vendor_payments')->sole()->credential('secret_key'))->toBe('sk_live_51NSECRETVALUE4f9K');
});

it('validates gateway input', function (): void {
    gatewayCurrencies();

    $this->actingAs(integrationsAdmin('integrations.manage'), 'admin')
        ->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), array_replace(stripeGatewayPayload(
            ['credentials' => ['publishable_key' => '', 'secret_key' => ''], 'currencies' => ['JPY'], 'mode' => 'sandbox'],
        ), ['fee_percent' => '120']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'fee_percent',
            'configs.vendor_payments.mode',
            'configs.vendor_payments.credentials.publishable_key',
            'configs.vendor_payments.credentials.secret_key',
        ]);
});

it('only activates a gateway once a config is enabled', function (): void {
    gatewayCurrencies();
    $admin = integrationsAdmin('integrations.manage');

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl('/admin/integrations/gateways/stripe/active'), ['is_active' => 1])
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), stripeGatewayPayload())->assertOk();

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl('/admin/integrations/gateways/stripe/active'), ['is_active' => 1])
        ->assertOk();
    expect(PaymentGateway::query()->where('code', 'stripe')->value('is_active'))->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl('/admin/integrations/gateways/cod/active'), ['is_active' => 1])
        ->assertOk();
});

it('disables the Active switch until an enabled config is saved (QA-P5-014)', function (): void {
    gatewayCurrencies();
    $admin = integrationsAdmin('integrations.manage');
    $switch = fn (string $html): string => preg_match('/<input[^>]*name="gateway_active"[^>]*>/s', $html, $match) ? $match[0] : '';

    $html = (string) $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/integrations/gateways/stripe/edit'))
        ->assertOk()
        ->assertSee(__('integrations::admin.gateways.activate_after_save'))
        ->getContent();
    expect($switch($html))->toContain('disabled');

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), stripeGatewayPayload())->assertOk();

    $html = (string) $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/integrations/gateways/stripe/edit'))
        ->assertOk()
        ->assertDontSee(__('integrations::admin.gateways.activate_after_save'))
        ->getContent();
    expect($switch($html))->not->toContain('disabled');
});

it('tests a gateway config and records the verification', function (): void {
    gatewayCurrencies();
    $admin = integrationsAdmin('integrations.manage');
    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), stripeGatewayPayload())->assertOk();

    Http::fake(['api.stripe.com/v1/balance' => Http::sequence()
        ->push(['object' => 'balance'])
        ->push(['error' => ['message' => 'Invalid API Key provided']], 401)]);

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/gateways/stripe/test/vendor_payments'))
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer sk_live_51NSECRETVALUE4f9K'));
    expect(PaymentGatewayConfig::query()->where('purpose', 'vendor_payments')->sole()->verified_at)->not->toBeNull();

    $response = $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/gateways/stripe/test/vendor_payments'))
        ->assertUnprocessable()
        ->assertJsonPath('data.error', 'auth_failed');
    expect($response->getContent())->not->toContain('sk_live_51NSECRETVALUE4f9K');

    $config = PaymentGatewayConfig::query()->where('purpose', 'vendor_payments')->sole();
    expect($config->verified_at)->toBeNull()->and($config->last_error)->toContain('auth_failed');
});

it('flags keys that do not match the selected mode', function (): void {
    gatewayCurrencies();
    $admin = integrationsAdmin('integrations.manage');
    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/gateways/stripe'), stripeGatewayPayload(['mode' => 'test']))
        ->assertOk();
    Http::fake();

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/gateways/stripe/test/vendor_payments'))
        ->assertUnprocessable()
        ->assertJsonPath('data.error', 'misconfigured');

    Http::assertNothingSent();
});

it('uses the sandbox host in test mode and the live host in live mode', function (PaymentGatewayCode $code, GatewayMode $mode, string $host, array $credentials): void {
    Http::fake(['*' => Http::response(['access_token' => 't', 'token' => 't', 'IsSuccess' => true])]);

    $result = app(GatewayConnectionTester::class)->test($code, $mode, $credentials);

    expect($result->ok)->toBeTrue();
    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), $host));
})->with([
    'paypal sandbox' => [PaymentGatewayCode::Paypal, GatewayMode::Test, 'https://api-m.sandbox.paypal.com/v1/oauth2/token', ['client_id' => 'c', 'client_secret' => 's']],
    'paypal live' => [PaymentGatewayCode::Paypal, GatewayMode::Live, 'https://api-m.paypal.com/v1/oauth2/token', ['client_id' => 'c', 'client_secret' => 's']],
    'myfatoorah test' => [PaymentGatewayCode::Myfatoorah, GatewayMode::Test, 'https://apitest.myfatoorah.com/v2/InitiatePayment', ['api_key' => 'k']],
    'tamara live' => [PaymentGatewayCode::Tamara, GatewayMode::Live, 'https://api.tamara.co/checkout/payment-types', ['api_token' => 'k']],
    'paymob' => [PaymentGatewayCode::Paymob, GatewayMode::Live, 'https://accept.paymob.com/api/auth/tokens', ['api_key' => 'k', 'integration_id' => '1', 'hmac_secret' => 'h']],
]);
