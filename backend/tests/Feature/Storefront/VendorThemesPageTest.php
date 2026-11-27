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
| VEN-030 / 030a / 030b / 031 Vendor › Your store › Themes and Themes by country (decision
| #119: 13 separate themes, no family / variant UI): active themes allowed in the selling
| countries, store default + per-country theme, refused countries, reset a country, reset
| to the platform default, signed storefront preview, permissions and tenant isolation.
*/

beforeEach(function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    $this->sa = Country::factory()->iso('SA')->create();
    $this->ae = Country::factory()->iso('AE')->create();
    $this->eg = Country::factory()->iso('EG')->create();
    $this->tenant = $this->createTenant('thm-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/themes'.$path);
    $this->byCountryUrl = $this->tenantUrl($this->tenant, 'admin/store/home-variants');
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $this->id = fn (string $slug): int => (int) Theme::query()->where('slug', $slug)->value('id');
    $this->config = fn (): array => $this->tenant->run(fn (): array => app(StoreThemeService::class)->config());
    $this->fresh = ['default' => null, 'countries' => [], 'saved' => false];
});

it('sends guests to the login and guards every endpoint with store.themes permissions', function (): void {
    $elora = ($this->id)('elora-1');
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->get($this->byCountryUrl)->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.themes.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();
    $this->getJson($this->byCountryUrl)->assertForbidden();
    $this->getJson(($this->url)('/'.$elora.'/preview'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('content::vendor_themes.subtitle'))
        ->assertDontSee('data-remote-modal="vendor-theme-modal"', false)
        ->assertDontSee(__('content::vendor_themes.activate_default'));
    $this->get($this->byCountryUrl)->assertOk()->assertDontSee('vendor-theme-scope-modal', false);
    $this->getJson(($this->url)('/'.$elora.'/countries'))->assertForbidden();
    $this->postJson(($this->url)('/activate'), ['theme' => $elora, 'default' => 1])->assertForbidden();
    $this->getJson(($this->url)('/scope/default'))->assertForbidden();
    $this->putJson(($this->url)('/scope/default'), ['theme' => $elora])->assertForbidden();
    $this->deleteJson(($this->url)('/countries/'.$this->sa->id))->assertForbidden();
    $this->deleteJson(($this->url)('/selection'))->assertForbidden();
});

it('lists the active themes allowed in the selling countries as separate themes, without variants', function (): void {
    Theme::query()->where('slug', 'souqify-6')->update(['is_active' => false]);
    Theme::query()->where('slug', 'souqify-3')->firstOrFail()->countries()->attach($this->eg->id);
    Theme::query()->where('slug', 'elora-2')->firstOrFail()->countries()->attach($this->sa->id);
    $name = fn (string $slug): string => (string) Theme::query()->where('slug', $slug)->firstOrFail()->name;
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()
        ->assertSee('data-page="vendor-themes"', false)
        ->assertSee($name('ecommet'))->assertSee($name('elora-1'))->assertSee($name('elora-6'))->assertSee($name('souqify-1'))
        ->assertDontSee($name('souqify-6'))
        ->assertDontSee($name('souqify-3'))
        ->assertSee('data-theme-id="'.($this->id)('elora-2').'"', false)
        ->assertSee(__('content::vendor_themes.available_in', ['countries' => $this->sa->name]))
        ->assertSee(trans_choice('content::vendor_themes.themes_count', 11, ['count' => 11]))
        ->assertSee(__('content::vendor_themes.current_title'))
        ->assertSee($this->sa->name)->assertSee($this->ae->name)->assertDontSee($this->eg->name)
        ->assertDontSee('Home v');

    // Hidden themes cannot be opened, previewed or activated.
    $this->get(($this->url)('/'.($this->id)('souqify-3').'/countries'), $this->ajax)->assertNotFound();
    $this->get(($this->url)('/'.($this->id)('souqify-6').'/preview'))->assertNotFound();
    $this->postJson(($this->url)('/activate'), ['theme' => ($this->id)('souqify-3'), 'default' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    $this->postJson(($this->url)('/activate'), ['theme' => ($this->id)('souqify-6'), 'default' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    $this->get(($this->url)('/elora-1/preview'))->assertNotFound();

    expect(($this->config)())->toBe($this->fresh);
});

it('shows the empty state without themes and the no-countries notice without selling countries', function (): void {
    // No selling country list = every active country; with none active the store sells nowhere yet.
    $bare = $this->createTenant('thm-bare-'.Str::lower(Str::random(6)), ['selling_country_ids' => []]);
    $owner = $this->createVendorOwner($bare);
    Country::query()->update(['is_active' => false]);
    $this->actingAsVendor($owner, $bare);

    $this->get($this->tenantUrl($bare, 'admin/store/themes'))->assertOk()
        ->assertSee('data-theme-no-countries', false)
        ->assertSee(__('content::vendor_themes.no_countries'))
        ->assertSee($this->tenantUrl($bare, 'admin/onboarding/setup?step=countries'), false)
        ->assertDontSee('data-remote-modal="vendor-theme-modal"', false);

    Theme::query()->update(['is_active' => false]);
    $this->app->forgetScopedInstances(); // a new request (the page service memoises the catalogue)
    $this->get($this->tenantUrl($bare, 'admin/store/themes'))->assertOk()->assertSee(__('content::vendor_themes.empty.title'));
    $this->get($this->tenantUrl($bare, 'admin/store/home-variants'))->assertOk()->assertSee(__('content::vendor_themes.empty.title'));
});

it('activates a theme as the store default and in countries, shown as Default / Active in chips', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $souqify = ($this->id)('souqify-2');
    $elora = ($this->id)('elora-3');

    $this->postJson(($this->url)('/activate'), ['theme' => $souqify, 'default' => 1])
        ->assertOk()->assertJsonPath('redirect', ($this->url)());
    expect(($this->config)()['default'])->toBe($souqify);

    $this->get(($this->url)('/'.$elora.'/countries'), $this->ajax)->assertOk()
        ->assertSee('vendor-theme-countries-form', false)
        ->assertSee($this->sa->name)->assertDontSee($this->eg->name);
    $this->get(($this->url)('/'.$elora.'/countries'))->assertOk()->assertSee('vendor-theme-countries-form', false);
    $this->get(($this->url)('/999999/countries'), $this->ajax)->assertNotFound();

    $this->postJson(($this->url)('/activate'), ['theme' => $elora, 'country_ids' => [$this->sa->id, $this->ae->id], 'default' => 0])->assertOk();

    $config = ($this->config)();
    expect($config['default'])->toBe($souqify)
        ->and($config['countries'])->toBe([$this->sa->id => $elora, $this->ae->id => $elora]);

    $countries = collect([$this->sa->name, $this->ae->name])->sort(fn ($a, $b) => (new Collator('en'))->compare($a, $b))->implode(', ');
    $this->get(($this->url)())->assertOk()
        ->assertSee(__('content::vendor_themes.default_chip'))
        ->assertSee(__('content::vendor_themes.active_in', ['countries' => $countries]))
        ->assertSee(__('content::vendor_themes.own_theme'));

    $this->get($this->byCountryUrl)->assertOk()
        ->assertSee(Theme::query()->find($elora)->name)
        ->assertSee(__('content::vendor_themes.own_theme'))
        ->assertSee(__('content::vendor_themes.chosen_default'));
});

it('refuses countries the theme is not allowed in or the store does not sell in, and empty targets', function (): void {
    Theme::query()->where('slug', 'elora-2')->firstOrFail()->countries()->attach($this->sa->id);
    $elora2 = ($this->id)('elora-2');
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/activate'), ['theme' => $elora2, 'country_ids' => [$this->ae->id]])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_ids' => __('content::vendor_themes.errors.country_not_allowed', ['theme' => Theme::query()->find($elora2)->name])]);
    $this->putJson(($this->url)('/scope/'.$this->ae->id), ['theme' => $elora2])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_ids']);
    $this->postJson(($this->url)('/activate'), ['theme' => ($this->id)('elora-1'), 'country_ids' => [$this->eg->id]])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_ids.0']);
    $this->postJson(($this->url)('/activate'), ['theme' => 'elora-1', 'default' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    $this->postJson(($this->url)('/activate'), ['theme' => ($this->id)('elora-1')])
        ->assertUnprocessable()->assertJsonValidationErrors(['country_ids']);

    // The countries modal only offers the selling countries the theme is allowed in.
    $this->get(($this->url)('/'.$elora2.'/countries'), $this->ajax)->assertOk()
        ->assertSee($this->sa->name)->assertDontSee($this->ae->name)
        ->assertSee(__('content::vendor_themes.fields.countries_restricted'));

    $this->getJson(($this->url)('/scope/'.$this->eg->id))->assertNotFound();
    $this->deleteJson(($this->url)('/countries/'.$this->eg->id))->assertNotFound();

    // Allowed country works.
    $this->postJson(($this->url)('/activate'), ['theme' => $elora2, 'country_ids' => [$this->sa->id]])->assertOk();
    expect(($this->config)()['countries'])->toBe([$this->sa->id => $elora2]);
});

it('changes the theme of one scope from Themes by country (VEN-030b / 031)', function (): void {
    Theme::query()->where('slug', 'elora-2')->firstOrFail()->countries()->attach($this->sa->id);
    $this->actingAsVendor($this->owner, $this->tenant);
    $elora4 = ($this->id)('elora-4');
    $souqify5 = ($this->id)('souqify-5');

    $this->get($this->byCountryUrl)->assertOk()
        ->assertSee($this->sa->name)->assertSee($this->ae->name)
        ->assertSee(__('content::vendor_themes.follows_default'))
        ->assertSee(__('content::vendor_themes.follows_platform'))
        ->assertSee('class="grid grid-cols-1 gap-6"', false)
        ->assertSee('class="relative overflow-x-auto"', false);

    $this->get(($this->url)('/scope/'.$this->ae->id.'?return=by-country'), $this->ajax)->assertOk()
        ->assertSee('vendor-theme-scope-form', false)
        ->assertSee(Theme::query()->find($elora4)->name)
        ->assertDontSee(Theme::query()->where('slug', 'elora-2')->first()->name)
        ->assertSee('value="by-country"', false);

    $this->putJson(($this->url)('/scope/'.$this->ae->id), ['theme' => $elora4, 'return' => 'by-country'])
        ->assertOk()->assertJsonPath('redirect', $this->byCountryUrl);
    $this->putJson(($this->url)('/scope/default'), ['theme' => $souqify5])->assertOk()->assertJsonPath('redirect', ($this->url)());
    $this->putJson(($this->url)('/scope/default'), ['theme' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    $this->putJson(($this->url)('/scope/default'), ['theme' => 999999])->assertUnprocessable()->assertJsonValidationErrors(['theme']);

    $config = ($this->config)();
    expect($config['default'])->toBe($souqify5)->and($config['countries'])->toBe([$this->ae->id => $elora4]);
});

it('resets a country to the store default and the whole store to the platform default', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $elora = ($this->id)('elora-2');
    $this->postJson(($this->url)('/activate'), ['theme' => $elora, 'country_ids' => [$this->sa->id, $this->ae->id], 'default' => 1])->assertOk();

    $this->deleteJson(($this->url)('/countries/'.$this->sa->id))
        ->assertOk()->assertJsonPath('redirect', ($this->url)());
    $this->deleteJson(($this->url)('/countries/'.$this->ae->id), ['return' => 'by-country'])
        ->assertOk()->assertJsonPath('redirect', $this->byCountryUrl);
    expect(($this->config)())->toBe(['default' => $elora, 'countries' => [], 'saved' => true]);

    $this->get(($this->url)())->assertOk()->assertSee(__('content::vendor_themes.reset_platform'));

    $this->deleteJson(($this->url)('/selection'))->assertOk()->assertJsonPath('redirect', ($this->url)());
    expect(($this->config)())->toBe(['default' => null, 'countries' => [], 'saved' => true])
        ->and($this->tenant->run(fn (): ?string => app(StoreThemeService::class)->resolveTheme(null)?->slug))->toBe('ecommet');

    $this->get(($this->url)())->assertOk()->assertDontSee(__('content::vendor_themes.reset_platform'));
});

it('previews a theme on the storefront through a signed, short-lived link only', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $location = $this->get(($this->url)('/'.($this->id)('elora-3').'/preview?country='.$this->ae->id))->assertRedirect()->headers->get('Location');
    expect($location)->toStartWith($this->tenantUrl($this->tenant, '/'))->toContain('theme_preview=elora-3')->toContain('tp_signature=');

    // A forged link changes nothing.
    $this->get($this->tenantUrl($this->tenant, '/?theme_preview=souqify-2&tp_expires='.(now()->addMinute()->timestamp).'&tp_signature=forged'))
        ->assertOk()->assertSessionMissing(ThemePreviewService::SESSION_KEY);

    $this->get($location)->assertOk()
        ->assertSessionHas(ThemePreviewService::SESSION_KEY, fn (array $preview): bool => $preview['slug'] === 'elora-3')
        ->assertSessionHas(StorefrontContext::COUNTRY_SESSION_KEY, $this->ae->id);

    // The saved theme is untouched; the preview ends on request.
    expect(($this->config)())->toBe($this->fresh);
    $this->get($this->tenantUrl($this->tenant, '/?theme_preview=off'))->assertOk()->assertSessionMissing(ThemePreviewService::SESSION_KEY);
});

it('shows the "Previewing theme · Exit" bar on the storefront in every family (QA-P8-009)', function (string $slug): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $location = $this->get(($this->url)('/'.($this->id)($slug).'/preview'))->assertRedirect()->headers->get('Location');
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
    $this->postJson(($this->url)('/activate'), ['theme' => ($this->id)('elora-5'), 'default' => 1])->assertOk();

    expect($other->run(fn (): array => app(StoreThemeService::class)->config()))->toBe($this->fresh);

    // This store's staff cannot act on the other store's host.
    $status = $this->postJson($this->tenantUrl($other, 'admin/store/themes/activate'), ['theme' => ($this->id)('souqify-1'), 'default' => 1])->getStatusCode();
    expect($status)->not->toBe(200)
        ->and($other->run(fn (): array => app(StoreThemeService::class)->config()))->toBe($this->fresh);
});
