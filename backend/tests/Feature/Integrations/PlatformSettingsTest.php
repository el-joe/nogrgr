<?php

use App\Modules\Integrations\Enums\ConnectionStatus;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Models\IntegrationSetting;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/IntegrationsHelpers.php';

/*
| Decision #69: business settings that were .env (billing, checkout limits, DNS edge IP)
| live in Admin › Settings › Platform; geocoding is an Integrations tab. Config files hold
| safe defaults only and the overlay applies saved values.
*/

dataset('platform endpoints', [
    'index' => ['get', '/admin/settings/platform', 'settings.view'],
    'show' => ['get', '/admin/settings/platform/billing', 'settings.view'],
    'update' => ['put', '/admin/settings/platform/commerce', 'settings.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $this->{$method}($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
})->with('platform endpoints');

it('forbids admins without the settings permission', function (string $method, string $path, string $permission): void {
    $admin = $permission === 'settings.manage' ? integrationsAdmin('settings.view') : integrationsAdmin();

    $this->actingAs($admin, 'admin')->{$method.'Json'}($this->centralUrl($path))->assertForbidden();
})->with('platform endpoints');

it('opens billing first and renders every platform tab', function (IntegrationSection $section): void {
    $admin = integrationsAdmin('settings.manage');

    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/settings/platform'))
        ->assertRedirect(route('admin.settings.platform.show', IntegrationSection::Billing));

    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/settings/platform/'.$section->value))
        ->assertOk()
        ->assertSee(__('integrations::admin.sections.'.$section->value.'.title'))
        ->assertSee(route('admin.settings.platform.update', $section), false)
        ->assertSee(route('admin.settings.platform.show', IntegrationSection::Domains), false)
        ->assertDontSee(route('admin.integrations.gateways.index'), false);
})->with(IntegrationSection::business());

it('keeps business sections off the integrations URLs and integrations off the platform URLs', function (): void {
    $admin = integrationsAdmin('integrations.manage', 'settings.manage');

    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/integrations/billing'))->assertNotFound();
    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/settings/platform/mail'))->assertNotFound();
});

it('uses safe defaults while nothing is saved (no env fallback)', function (): void {
    expect(config('billing.tax_rate_bp'))->toBe(0)
        ->and(config('billing.seller.name'))->toBe(config('app.name'))
        ->and(config('commerce.reservation_minutes'))->toBe(30)
        ->and(config('services.geocoding.enabled'))->toBeFalse()
        ->and(config('ai.providers.openai.api_key'))->toBeNull()
        ->and(config('openai.api_key'))->toBeNull()
        ->and(config('broadcasting.connections.reverb.key'))->toBeNull()
        ->and(config('mail.mailers.smtp.host'))->toBeNull()
        ->and(config('tenancy.custom_domain_records.0.value'))->toBe('');

    expect(RateLimiter::limiter('store-api')(Request::create('/store-api/v1/cart'))->maxAttempts)->toBe(120)
        ->and(RateLimiter::limiter('store-api-checkout')(Request::create('/store-api/v1/checkout'))->maxAttempts)->toBe(10);
});

it('saves billing, commerce and domain settings and overlays them on config', function (): void {
    $admin = integrationsAdmin('settings.manage');

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/settings/platform/billing'), [
        'tax_rate_bp' => 1500, 'invoice_due_days' => 10, 'grace_days' => 3,
        'seller_name' => 'Nogrgr LLC', 'seller_tax_number' => '300000000000003', 'seller_address' => "Riyadh\nKSA",
    ])->assertOk()->assertJsonPath('success', true);

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/settings/platform/commerce'), [
        'reservation_minutes' => 45, 'store_api_rate_limit' => 300, 'checkout_rate_limit' => 5,
    ])->assertOk();

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/settings/platform/domains'), ['edge_ipv4' => '203.0.113.10'])->assertOk();

    expect(IntegrationSetting::query()->where('group', 'billing')->where('key', 'tax_rate_bp')->value('value'))->toBe('1500')
        ->and(config('billing.tax_rate_bp'))->toBe(1500)
        ->and(config('billing.invoice_due_days'))->toBe(10)
        ->and(config('billing.grace_days'))->toBe(3)
        ->and(config('billing.seller.name'))->toBe('Nogrgr LLC')
        ->and(config('billing.seller.address'))->toBe("Riyadh\nKSA")
        ->and(config('commerce.reservation_minutes'))->toBe(45)
        ->and(config('tenancy.custom_domain_records.0'))->toMatchArray(['type' => 'A', 'host' => '@', 'value' => '203.0.113.10']);

    expect(RateLimiter::limiter('store-api')(Request::create('/store-api/v1/cart'))->maxAttempts)->toBe(300)
        ->and(RateLimiter::limiter('store-api-checkout')(Request::create('/store-api/v1/checkout'))->maxAttempts)->toBe(5);
});

it('validates platform settings ranges and formats', function (): void {
    $admin = integrationsAdmin('settings.manage');

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/settings/platform/billing'), ['tax_rate_bp' => 20000, 'invoice_due_days' => -1, 'grace_days' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_rate_bp', 'invoice_due_days', 'grace_days']);

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/settings/platform/commerce'), ['reservation_minutes' => 1, 'store_api_rate_limit' => '', 'checkout_rate_limit' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reservation_minutes', 'store_api_rate_limit']);

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/settings/platform/domains'), ['edge_ipv4' => '2001:db8::1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['edge_ipv4']);
});

it('enables geocoding from the integrations tab and tests both providers', function (): void {
    $admin = integrationsAdmin('integrations.manage');
    Http::fake([
        'nominatim.example.test/*' => Http::response(['address' => ['city' => 'Riyadh', 'country_code' => 'sa']]),
        'ip.example.test/*' => Http::response(['success' => true, 'country_code' => 'US']),
    ]);

    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/integrations/geocoding'))
        ->assertOk()
        ->assertSee(__('integrations::admin.sections.geocoding.title'))
        ->assertSee(ConnectionStatus::NotConfigured->label());

    $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/integrations/geocoding'), [
        'enabled' => '1', 'reverse_provider' => 'nominatim', 'ip_provider' => 'ipwhois',
        'nominatim_url' => 'https://nominatim.example.test', 'contact_email' => 'ops@example.test',
        'ipwhois_url' => 'https://ip.example.test', 'user_agent' => 'NogrgrStorefront/1.0', 'timeout' => 4, 'cache_days' => 2,
    ])->assertOk();

    expect(config('services.geocoding.enabled'))->toBeTrue()
        ->and(config('services.geocoding.nominatim.url'))->toBe('https://nominatim.example.test')
        ->and(config('services.geocoding.cache_ttl'))->toBe(2 * 86400);

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/geocoding/test'))->assertOk()->assertJsonPath('data.status', 'connected');

    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), 'https://nominatim.example.test/reverse') && $request['email'] === 'ops@example.test');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://ip.example.test/8.8.8.8');
});
