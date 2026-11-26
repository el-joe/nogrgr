<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Services\Themes\ThemePreviewService;
use Illuminate\Support\Str;

/*
| VEN-030 / 030a / 030b / 031 Vendor › Your store › Themes and Home variants: store default
| + per-country theme, home variant, reset, signed storefront preview, permissions,
| validation (country not sold, inactive theme) and tenant isolation.
*/

beforeEach(function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    $this->sa = Country::factory()->iso('SA')->create();
    $this->ae = Country::factory()->iso('AE')->create();
    $this->eg = Country::factory()->iso('EG')->create();
    $this->tenant = $this->createTenant('thm-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/themes'.$path);
    $this->variantsUrl = $this->tenantUrl($this->tenant, 'admin/store/home-variants');
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $this->config = fn (): array => $this->tenant->run(fn (): array => app(StoreThemeService::class)->config());
});

it('sends guests to the login and guards every endpoint with store.themes permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->get($this->variantsUrl)->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.themes.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();
    $this->getJson($this->variantsUrl)->assertForbidden();
    $this->getJson(($this->url)('/elora-1/preview'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('content::vendor_themes.subtitle'))->assertDontSee(__('content::vendor_themes.use_in_countries'));
    $this->get($this->variantsUrl)->assertOk()->assertDontSee(__('content::vendor_themes.change_variant'));
    $this->getJson(($this->url)('/elora-1/countries'))->assertForbidden();
    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-1', 'default' => 1])->assertForbidden();
    $this->getJson(($this->url)('/variant/default'))->assertForbidden();
    $this->putJson(($this->url)('/variant/default'), ['variant' => 2])->assertForbidden();
    $this->deleteJson(($this->url)('/countries/'.$this->sa->id))->assertForbidden();
});

it('shows the theme families, selling-country tabs and the active home variant', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('content::vendor_themes.store_default'))
        ->assertSee($this->sa->name)->assertSee($this->ae->name)->assertDontSee($this->eg->name)
        ->assertSee('Elora')->assertSee('Souqify')->assertSee('Ecommet')
        ->assertSee(__('content::vendor_themes.variant_section', ['theme' => 'Ecommet', 'scope' => __('content::vendor_themes.store_default')]))
        ->assertSee('data-page="vendor-themes"', false);

    $this->get(($this->url)('?country='.$this->sa->id))->assertOk()
        ->assertSee(__('content::vendor_themes.follows_default_notice', ['country' => $this->sa->name]));

    // QA-P8: the wide table scrolls inside a positioned wrapper (its sr-only header cannot
    // widen the page on phones) in a minmax(0, 1fr) grid.
    $this->get($this->variantsUrl)->assertOk()
        ->assertSee($this->sa->name)->assertSee($this->ae->name)
        ->assertSee(__('content::vendor_themes.follows_default'))
        ->assertSee('class="grid grid-cols-1 gap-6"', false)
        ->assertSee('class="relative overflow-x-auto"', false);
});

it('activates a theme as the store default and for a single country', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/activate'), ['theme' => 'souqify-2', 'default' => 1])
        ->assertOk()->assertJsonPath('redirect', ($this->url)());
    expect(($this->config)()['default'])->toBe('souqify-2');

    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-1', 'country_ids' => [$this->sa->id], 'scope' => $this->sa->id])
        ->assertOk()->assertJsonPath('redirect', ($this->url)('?country='.$this->sa->id));

    $config = ($this->config)();
    expect($config['default'])->toBe('souqify-2')
        ->and($config['countries'])->toBe([$this->sa->id => 'elora-1']);

    $this->get(($this->url)('?country='.$this->sa->id))->assertOk()
        ->assertSee(__('content::vendor_themes.own_theme_notice', ['country' => $this->sa->name]))
        ->assertSee(__('content::vendor_themes.active_in', ['scope' => $this->sa->name, 'variant' => 1]));
});

it('activates a theme in several countries from the countries modal (VEN-030a)', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)('/elora-3/countries'), $this->ajax)->assertOk()
        ->assertSee('vendor-theme-countries-form', false)
        ->assertSee($this->sa->name)->assertDontSee($this->eg->name);
    $this->get(($this->url)('/elora-3/countries'))->assertOk()->assertSee('vendor-theme-countries-form', false);
    $this->get(($this->url)('/no-such/countries'), $this->ajax)->assertNotFound();

    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-3', 'country_ids' => [$this->sa->id, $this->ae->id], 'default' => 0])->assertOk();

    expect(($this->config)()['countries'])->toBe([$this->sa->id => 'elora-3', $this->ae->id => 'elora-3']);
});

it('rejects countries the store does not sell in, inactive themes and empty targets', function (): void {
    Theme::query()->where('slug', 'souqify-6')->update(['is_active' => false]);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-1', 'country_ids' => [$this->eg->id]])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_ids.0']);
    $this->postJson(($this->url)('/activate'), ['theme' => 'souqify-6', 'default' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    $this->postJson(($this->url)('/activate'), ['theme' => 'nope', 'default' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-1'])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_ids']);

    $this->getJson(($this->url)('/variant/'.$this->eg->id))->assertNotFound();
    $this->putJson(($this->url)('/variant/'.$this->eg->id), ['variant' => 2])->assertNotFound();
    $this->deleteJson(($this->url)('/countries/'.$this->eg->id))->assertNotFound();
    $this->getJson(($this->url)('/souqify-6/preview'))->assertNotFound();

    expect(($this->config)())->toBe(['default' => null, 'countries' => []]);
});

it('picks the home variant of a country and of the store default (VEN-030b / 031)', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-1', 'country_ids' => [$this->ae->id]])->assertOk();

    $this->get(($this->url)('/variant/'.$this->ae->id.'?return=home-variants'), $this->ajax)->assertOk()
        ->assertSee('vendor-theme-variant-form', false)
        ->assertSee(__('content::vendor_themes.home_variant', ['number' => 6]))
        ->assertSee('value="home-variants"', false);

    $this->putJson(($this->url)('/variant/'.$this->ae->id), ['variant' => 4, 'return' => 'home-variants'])
        ->assertOk()->assertJsonPath('redirect', $this->variantsUrl);
    expect(($this->config)()['countries'][$this->ae->id])->toBe('elora-4');

    // Ecommet has a single home variant.
    $this->putJson(($this->url)('/variant/default'), ['variant' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors(['variant']);
    $this->putJson(($this->url)('/variant/default'), ['variant' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors(['variant']);

    $this->postJson(($this->url)('/activate'), ['theme' => 'souqify-1', 'default' => 1])->assertOk();
    $this->putJson(($this->url)('/variant/default'), ['variant' => 5])->assertOk();
    expect(($this->config)()['default'])->toBe('souqify-5');

    $this->get($this->variantsUrl)->assertOk()
        ->assertSee(__('content::vendor_themes.home_variant', ['number' => 5]))
        ->assertSee(__('content::vendor_themes.own_theme'));
});

it('resets a country to the store default', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-2', 'country_ids' => [$this->sa->id, $this->ae->id]])->assertOk();

    $this->deleteJson(($this->url)('/countries/'.$this->sa->id))
        ->assertOk()->assertJsonPath('redirect', ($this->url)('?country='.$this->sa->id));

    expect(($this->config)()['countries'])->toBe([$this->ae->id => 'elora-2']);
});

it('previews a theme on the storefront through a signed, short-lived link only', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $location = $this->get(($this->url)('/elora-3/preview?country='.$this->ae->id))->assertRedirect()->headers->get('Location');
    expect($location)->toStartWith($this->tenantUrl($this->tenant, '/'))->toContain('theme_preview=elora-3')->toContain('tp_signature=');

    // A forged link changes nothing.
    $this->get($this->tenantUrl($this->tenant, '/?theme_preview=souqify-2&tp_expires='.(now()->addMinute()->timestamp).'&tp_signature=forged'))
        ->assertOk()->assertSessionMissing(ThemePreviewService::SESSION_KEY);

    $this->get($location)->assertOk()
        ->assertSessionHas(ThemePreviewService::SESSION_KEY, fn (array $preview): bool => $preview['slug'] === 'elora-3')
        ->assertSessionHas(StorefrontContext::COUNTRY_SESSION_KEY, $this->ae->id);

    // The saved theme is untouched; the preview ends on request.
    expect(($this->config)())->toBe(['default' => null, 'countries' => []]);
    $this->get($this->tenantUrl($this->tenant, '/?theme_preview=off'))->assertOk()->assertSessionMissing(ThemePreviewService::SESSION_KEY);
});

it('shows the "Previewing theme · Exit" bar on the storefront in every family (QA-P8-009)', function (string $slug): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $location = $this->get(($this->url)('/'.$slug.'/preview'))->assertRedirect()->headers->get('Location');
    $name = Theme::query()->where('slug', $slug)->firstOrFail()->name;

    $this->get($location)->assertOk()
        ->assertSee('data-theme-preview-bar', false)
        ->assertSee(__('content::vendor_themes.preview_bar.text', ['theme' => $name]))
        ->assertSee('theme_preview=off', false);

    // Browsing on keeps the bar; exiting removes it.
    $this->get($this->tenantUrl($this->tenant, '/'))->assertOk()->assertSee('data-theme-preview-exit', false);
    $this->get($this->tenantUrl($this->tenant, '/?theme_preview=off'))->assertOk()->assertDontSee('data-theme-preview-bar', false);
})->with(['ecommet', 'elora-3', 'souqify-4']);

it('never shows the preview bar to a shopper without a preview session', function (): void {
    $this->get($this->tenantUrl($this->tenant, '/'))->assertOk()->assertDontSee('data-theme-preview-bar', false);
});

it('resolves the previewed theme in the storefront context and ignores expired links', function (): void {
    $this->tenant->run(function (): void {
        $service = app(ThemePreviewService::class);
        $url = $service->url($this->tenant, 'souqify-4');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $request = request();
        $request->setLaravelSession(app('session')->driver('array'));
        $request->query->replace($query);
        expect($service->current($request)?->slug)->toBe('souqify-4');

        $request->query->replace([]);
        expect($service->current($request)?->slug)->toBe('souqify-4');

        $this->travel(ThemePreviewService::PREVIEW_MINUTES + 1)->minutes();
        expect($service->current($request))->toBeNull();

        $request->query->replace($query);
        expect($service->current($request))->toBeNull();
    });
});

it('keeps each store on its own theme settings', function (): void {
    $other = $this->createTenant('thm-other-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$this->sa->id]]);
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-5', 'default' => 1])->assertOk();

    expect($other->run(fn (): array => app(StoreThemeService::class)->config()))->toBe(['default' => null, 'countries' => []]);

    // This store's staff cannot act on the other store's host.
    $status = $this->postJson($this->tenantUrl($other, 'admin/store/themes/activate'), ['theme' => 'souqify-1', 'default' => 1])->getStatusCode();
    expect($status)->not->toBe(200)
        ->and($other->run(fn (): array => app(StoreThemeService::class)->config()))->toBe(['default' => null, 'countries' => []]);
});
