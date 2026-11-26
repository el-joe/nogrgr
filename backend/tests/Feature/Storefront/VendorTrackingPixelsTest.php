<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Storefront\Services\Pages\StorePageService;
use Illuminate\Support\Str;

/*
| VEN-083 Vendor › Settings › Tracking pixels: Meta / TikTok / Snap / GA4 ids with on / off,
| stored per store; the storefront gets the enabled ones as the #sf-tracking island and the
| consent notice — never a third-party <script> in the markup (decision #91).
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('trk-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->tenant->run(fn () => app(StorePageService::class)->ensureSystemPages('Megz'));
    $this->url = $this->tenantUrl($this->tenant, 'admin/settings/tracking');
    $this->setting = fn (): mixed => $this->tenant->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::TrackingPixels->value)->first()?->value);
});

function trackingPayload(array $overrides = []): array
{
    return ['pixels' => array_replace_recursive([
        'meta' => ['id' => '1022 3344 5566', 'enabled' => '1'],
        'tiktok' => ['id' => 'ck3bq5rc77u2example', 'enabled' => '1'],
        'snap' => ['id' => '', 'enabled' => '0'],
        'ga4' => ['id' => 'G-MEGZ1234', 'enabled' => '0'],
    ], $overrides)];
}

it('sends guests to the login and guards the page with settings.tracking permissions', function (): void {
    $this->get($this->url)->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.tracking.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson($this->url)->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get($this->url)->assertOk()->assertSee(__('content::store_tracking.subtitle'))->assertDontSee(__('content::store_tracking.save'));
    $this->putJson($this->url, trackingPayload())->assertForbidden();
});

it('validates and saves the pixel ids per network', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get($this->url)->assertOk()->assertSee(__('content::store_tracking.status.not_set'))->assertSee('Google Analytics 4');

    $this->putJson($this->url, trackingPayload([
        'meta' => ['id' => 'abc'],
        'snap' => ['id' => '', 'enabled' => '1'],
        'ga4' => ['id' => 'UA-1234'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['pixels.meta.id', 'pixels.snap.id', 'pixels.ga4.id']);

    $this->putJson($this->url, trackingPayload())->assertOk();

    expect(($this->setting)())->toEqual([
        'meta' => ['id' => '102233445566', 'enabled' => true],
        'tiktok' => ['id' => 'CK3BQ5RC77U2EXAMPLE', 'enabled' => true],
        'snap' => ['id' => null, 'enabled' => false],
        'ga4' => ['id' => 'G-MEGZ1234', 'enabled' => false],
    ]);

    $this->get($this->url)->assertOk()->assertSee('102233445566')->assertSee(__('content::store_tracking.status.receiving'))->assertSee(__('content::store_tracking.status.off'));
});

it('hands only the enabled pixels to the storefront behind the consent notice', function (): void {
    $page = $this->tenantUrl($this->tenant, '/pages/about');
    expect($this->get($page)->assertOk()->getContent())->not->toContain('sf-tracking')->not->toContain('data-tracking-consent');

    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson($this->url, trackingPayload())->assertOk();

    $html = $this->get($page)->assertOk()->getContent();
    preg_match('#<script type="application/json" id="sf-tracking">(.+?)</script>#s', $html, $island);

    expect(json_decode($island[1], true))->toBe(['pixels' => ['meta' => '102233445566', 'tiktok' => 'CK3BQ5RC77U2EXAMPLE'], 'cookie' => 'sf_tracking_consent'])
        ->and($html)->toContain('data-tracking-consent')->toContain(__st('appearance.consent.accept'))
        // No third-party script tag: the bundled loader injects them after consent only.
        ->not->toContain('connect.facebook.net')->not->toContain('analytics.tiktok.com')->not->toContain('googletagmanager.com');

    $this->putJson($this->url, trackingPayload(['meta' => ['enabled' => '0'], 'tiktok' => ['enabled' => '0']]))->assertOk();
    expect($this->get($page)->getContent())->not->toContain('sf-tracking');
});

it('keeps the pixels of each store apart', function (): void {
    $other = $this->createTenant('trk-o-'.Str::lower(Str::random(6)));
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson($this->url, trackingPayload())->assertOk();

    $other->run(fn () => expect(StoreSetting::query()->where('key', StoreSettingKey::TrackingPixels->value)->exists())->toBeFalse());
});
