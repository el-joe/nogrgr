<?php

use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Support\StorefrontView;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Storefront foundation (decision #76): theme per country (store_theme setting), the
| request context (country / currency) and the locale switcher endpoint.
*/

it('resolves the store theme per country and falls back to the default', function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    $shop = shopSetup('themed');

    $shop->tenant->run(function () use ($shop): void {
        $themes = app(StoreThemeService::class);
        expect($themes->resolve($shop->sa->id)->family)->toBe(ThemeFamily::Ecommet);

        $themes->activate('ecommet');
        $themes->setCountryVariant($shop->sa->id, ThemeFamily::Elora, 3);

        $fresh = app()->make(StoreThemeService::class);
        expect($fresh->resolve($shop->sa->id)->family)->toBe(ThemeFamily::Elora)
            ->and($fresh->resolve($shop->sa->id)->variant)->toBe(3)
            ->and($fresh->resolve($shop->ae->id)->slug)->toBe('ecommet');

        $fresh->resetCountry($shop->sa->id);
        expect(app()->make(StoreThemeService::class)->resolve($shop->sa->id)->slug)->toBe('ecommet');
    });
});

it('rejects unknown themes and countries the store does not sell to', function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    $shop = shopSetup('themed-invalid');

    $shop->tenant->run(function (): void {
        expect(fn () => app(StoreThemeService::class)->activate('no-such-theme'))->toThrow(ValidationException::class)
            ->and(fn () => app(StoreThemeService::class)->activate('ecommet', [999999]))->toThrow(ValidationException::class);
    });
});

it('falls back to the ecommet views for families without their own page', function (): void {
    $shop = shopSetup('view-fallback');
    $shop->tenant->forceFill(['theme_family' => 'souqify'])->save();

    $shop->tenant->run(function (): void {
        expect(app(StorefrontContext::class)->family())->toBe('souqify')
            ->and(StorefrontView::resolve('pages.cart'))->toBe('storefront::themes.ecommet.pages.cart');
    });
});

it('stores the switcher country and currency and redirects with the language', function (): void {
    $shop = shopSetup('prefs');

    $this->post($this->tenantUrl($shop->tenant, '/preferences'), [
        'country_id' => $shop->ae->id,
        'locale' => 'ar',
        'redirect' => '/new-in',
    ])->assertRedirect($this->tenantUrl($shop->tenant, '/new-in?lang=ar'))
        ->assertSessionHas(StorefrontContext::COUNTRY_SESSION_KEY, $shop->ae->id);
});

it('refuses a country outside the store and an off-site redirect', function (): void {
    $shop = shopSetup('prefs-invalid');

    $this->post($this->tenantUrl($shop->tenant, '/preferences'), ['country_id' => 999999])
        ->assertSessionHasErrors('country_id');

    $this->post($this->tenantUrl($shop->tenant, '/preferences'), ['redirect' => 'https://evil.test/x'])
        ->assertRedirect(rtrim($this->tenantUrl($shop->tenant, '/'), '/'));
});
