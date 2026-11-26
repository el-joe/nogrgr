<?php

use App\Modules\Api\DTOs\MobileAppSettingsData;
use App\Modules\Api\DTOs\PlatformReleaseData;
use App\Modules\Api\Services\MobileAppSettingsService;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;
use App\Modules\Notifications\Models\CustomerDevice;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/MobileApiHelpers.php';

/*
| Mobile API v1 bootstrap (APP-001 … APP-003, APP-029): store branding, languages,
| display-only currencies (charge = USD), countries, feature flags, force / optional update
| and maintenance from the store's DB settings, static texts with ETag, push devices.
*/

beforeEach(function (): void {
    $this->sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $this->tenant = $this->createTenant('shop', ['selling_country_ids' => [$this->sa->id], 'default_country_id' => $this->sa->id]);
});

it('returns the store bootstrap with USD as the charge currency', function (): void {
    $this->getJson(mobileApi('bootstrap'), mobileHeaders($this->tenant))
        ->assertOk()
        ->assertJsonPath('data.store.id', $this->tenant->id)
        ->assertJsonPath('data.currency.charge', 'USD')
        ->assertJsonPath('data.currency.base', 'USD')
        ->assertJsonPath('data.countries.default_id', $this->sa->id)
        ->assertJsonPath('data.app.update.state', 'none')
        ->assertJsonPath('data.app.maintenance.enabled', false)
        ->assertJsonPath('data.features.wishlist', true)
        ->assertJsonPath('data.push.enabled', false)
        ->assertJsonStructure(['data' => [
            'branding' => ['theme' => ['slug', 'family', 'variant'], 'colors' => ['primary'], 'fonts', 'splash'],
            'locale' => ['current', 'dir', 'default', 'languages'],
            'texts' => ['version', 'locale'],
        ]]);
});

it('forces or offers an update from the store settings and shows maintenance', function (): void {
    $this->tenant->run(fn () => app(MobileAppSettingsService::class)->save(new MobileAppSettingsData(
        android: new PlatformReleaseData('2.0.0', '2.3.0', 'https://play.google.com/store/apps/details?id=shop'),
        maintenance: true,
        maintenanceMessage: ['en' => 'Back at 10:00', 'ar' => 'نعود الساعة 10:00'],
        features: ['wishlist' => false],
    )));

    $this->getJson(mobileApi('bootstrap?platform=android&app_version=1.9.4'), mobileHeaders($this->tenant))
        ->assertOk()
        ->assertJsonPath('data.app.update.state', 'required')
        ->assertJsonPath('data.app.update.store_url', 'https://play.google.com/store/apps/details?id=shop')
        ->assertJsonPath('data.app.maintenance.enabled', true)
        ->assertJsonPath('data.app.maintenance.message', 'Back at 10:00')
        ->assertJsonPath('data.features.wishlist', false);

    $this->getJson(mobileApi('bootstrap'), mobileHeaders($this->tenant, extra: ['X-App-Platform' => 'android', 'X-App-Version' => '2.1.0', 'X-Locale' => 'ar']))
        ->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data.locale.dir', 'rtl')
        ->assertJsonPath('data.app.update.state', 'optional')
        ->assertJsonPath('data.app.maintenance.message', 'نعود الساعة 10:00');

    $this->getJson(mobileApi('bootstrap?platform=ios&app_version=1.0.0'), mobileHeaders($this->tenant))
        ->assertJsonPath('data.app.update.state', 'none');
});

it('validates the bootstrap query', function (): void {
    $this->getJson(mobileApi('bootstrap?platform=windows&app_version=abc'), mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform', 'app_version']);
});

it('serves the store static texts with its edits, a group filter and an ETag', function (): void {
    $this->tenant->run(function (): void {
        TenantStaticText::query()->updateOrCreate(['key' => 'mobile.tabs.home', 'locale' => 'en'], ['value' => 'Shop', 'source' => 'manual', 'is_locked' => true]);
        app(StaticTextResolver::class)->forget();
    });

    $response = $this->getJson(mobileApi('texts?group=mobile'), mobileHeaders($this->tenant))
        ->assertOk();

    expect($response->json('data.texts')['mobile.tabs.home'])->toBe('Shop')
        ->and(array_keys($response->json('data.texts')))->each->toStartWith('mobile.');

    $etag = $response->headers->get('ETag');
    $this->getJson(mobileApi('texts?group=mobile'), mobileHeaders($this->tenant, extra: ['If-None-Match' => (string) $etag]))->assertStatus(304);

    $this->getJson(mobileApi('texts?group=mobile'), mobileHeaders($this->tenant, extra: ['X-Locale' => 'ar']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'ar')
        ->assertJson(['data' => ['texts' => ['mobile.tabs.cart' => 'السلة']]]);
});

it('registers a guest install, binds it on sign-in and refuses removing another customer install', function (): void {
    $customer = $this->createCustomer($this->tenant, ['email' => 'sara@example.test']);
    $other = $this->createCustomer($this->tenant);

    $this->postJson(mobileApi('devices'), ['device_id' => 'install-guest-1', 'platform' => 'ios', 'push_token' => 'fcm-token-1', 'app_version' => '1.2.0'], mobileHeaders($this->tenant))
        ->assertOk()
        ->assertJsonPath('data.device.signed_in', false)
        ->assertJsonPath('data.device.has_push_token', true)
        ->assertJsonMissingPath('data.device.push_token');

    $tokens = mobileLogin($this->tenant, 'sara@example.test', deviceId: 'install-guest-1');

    $this->tenant->run(fn () => expect(CustomerDevice::query()->where('device_id', 'install-guest-1')->value('customer_id'))->toBe($customer->id));

    $this->getJson(mobileApi('devices'), mobileHeaders($this->tenant, $tokens['access_token']))
        ->assertOk()
        ->assertJsonCount(1, 'data.devices');

    $this->postJson(mobileApi('devices'), ['device_id' => 'install-guest-2', 'platform' => 'android', 'push_token' => 'fcm-token-1'], mobileHeaders($this->tenant, $tokens['access_token']))
        ->assertOk()
        ->assertJsonPath('data.device.signed_in', true);

    $this->tenant->run(function () use ($other): void {
        expect(CustomerDevice::query()->where('device_id', 'install-guest-1')->value('push_token'))->toBeNull();
        CustomerDevice::factory()->create(['device_id' => 'install-other-1', 'customer_id' => $other->id]);
    });

    $this->deleteJson(mobileApi('devices/install-other-1'), [], mobileHeaders($this->tenant, $tokens['access_token']))->assertNotFound();
    $this->deleteJson(mobileApi('devices/install-guest-2'), [], mobileHeaders($this->tenant, $tokens['access_token']))->assertOk();

    $this->postJson(mobileApi('auth/logout'), [], mobileHeaders($this->tenant, $tokens['access_token']))->assertOk();
    $this->tenant->run(fn () => expect(CustomerDevice::query()->where('device_id', 'install-guest-1')->value('customer_id'))->toBeNull());
});

it('validates device registration', function (): void {
    $this->postJson(mobileApi('devices'), ['device_id' => 'x', 'platform' => 'windows'], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_id', 'platform']);
});

it('keeps devices and settings inside their store', function (): void {
    $other = $this->createTenant('other');
    $this->tenant->run(fn () => app(MobileAppSettingsService::class)->save(new MobileAppSettingsData(maintenance: true)));
    Sanctum::actingAs($this->createCustomer($other), ['api'], 'sanctum');

    $this->getJson(mobileApi('bootstrap'), mobileHeaders($other))->assertOk()->assertJsonPath('data.app.maintenance.enabled', false);
});
