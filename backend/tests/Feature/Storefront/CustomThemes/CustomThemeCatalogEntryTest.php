<?php

use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\CustomTheme;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Support\CustomThemes\CustomThemeCsp;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| #119 / #121 seam: the store's published custom Twig theme is a selectable entry on
| Vendor › Themes (use / stop = the custom theme switch, back to the themes page), and the
| custom theme CSP opens the four approved pixel hosts (#91) only once consent is given.
*/

function publishedCustomTheme(int $tenantId, bool $active, CustomThemeVersionStatus $status = CustomThemeVersionStatus::Approved): CustomThemeVersion
{
    $theme = CustomTheme::withoutTenantScope()->create(['tenant_id' => $tenantId, 'name' => 'Aurora', 'is_active' => $active]);
    $version = CustomThemeVersion::withoutTenantScope()->create([
        'tenant_id' => $tenantId,
        'custom_theme_id' => $theme->id,
        'version' => '1.2.0',
        'status' => $status->value,
        'name' => 'Aurora',
        'report' => [],
        'checksum' => str_repeat('a', 64),
        'reviewed_at' => now(),
        'published_at' => now(),
    ]);
    $theme->forceFill(['live_version_id' => $version->id])->save();

    return $version;
}

beforeEach(function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    [$this->tenant, $this->owner] = catalogStore('ctc-'.Str::lower(Str::random(6)));
    $this->url = $this->tenantUrl($this->tenant, 'admin/store/themes');
});

it('lists the published custom theme as its own entry with use / stop actions', function (): void {
    $version = publishedCustomTheme($this->tenant->id, false);

    $this->actingAsVendor($this->owner, $this->tenant)->get($this->url)
        ->assertOk()
        ->assertSee('data-custom-theme-entry', false)
        ->assertSee('Aurora')
        ->assertSee(__('content::vendor_themes.custom.use'))
        ->assertSee('return=themes', false)
        ->assertSee('custom-theme/versions/'.$version->id.'/preview', false);

    $this->actingAsVendor($this->owner, $this->tenant)
        ->patchJson($this->tenantUrl($this->tenant, 'admin/store/custom-theme/active?active=1&return=themes'))
        ->assertOk()
        ->assertJsonPath('redirect', fn (string $url) => str_contains($url, 'store/themes'));

    expect(CustomTheme::withoutTenantScope()->where('tenant_id', $this->tenant->id)->value('is_active'))->toBeTrue();

    $this->actingAsVendor($this->owner, $this->tenant)->get($this->url)
        ->assertOk()
        ->assertSee(__('content::vendor_themes.custom.stop'));
});

it('hides the entry while no version is published and from staff without the custom theme permission', function (): void {
    publishedCustomTheme($this->tenant->id, false, CustomThemeVersionStatus::PendingReview);

    $this->actingAsVendor($this->owner, $this->tenant)->get($this->url)
        ->assertOk()
        ->assertDontSee('data-custom-theme-entry', false);

    CustomTheme::withoutTenantScope()->where('tenant_id', $this->tenant->id)->delete();
    publishedCustomTheme($this->tenant->id, true);
    $viewer = catalogStaff($this->tenant, ['store.themes.view']);

    $this->actingAsVendor($viewer, $this->tenant)->get($this->url)
        ->assertOk()
        ->assertDontSee('data-custom-theme-entry', false);
});

it('opens only the hosts of consented pixels in the custom theme CSP', function (): void {
    $csp = new CustomThemeCsp;
    $policy = $csp->policy();

    expect($policy)->not->toContain('facebook')->not->toContain('googletagmanager');

    $csp->allowTracking(['meta', 'ga4', 'evil']);
    $policy = $csp->policy();

    expect($csp->trackingPixels())->toBe(['meta', 'ga4'])
        ->and($policy)->toContain("script-src 'self' 'nonce-")
        ->and($policy)->toContain('https://connect.facebook.net')
        ->and($policy)->toContain('https://www.googletagmanager.com')
        ->and($policy)->not->toContain('analytics.tiktok.com')
        ->and($policy)->not->toContain('sc-static.net')
        ->and($policy)->toContain("frame-src 'none'");
});
