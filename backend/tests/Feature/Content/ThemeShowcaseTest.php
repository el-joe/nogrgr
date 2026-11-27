<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Support\DemoStore;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';
require_once __DIR__.'/../Platform/Registration/SignUpHelpers.php';

/*
| WEB-004 central theme gallery + live preview (decisions #117 a / #119): only active
| catalogue themes, a noindex preview per theme framed from the platform demo store, the
| "Use this template" CTA preselecting the theme at registration, and the read-only demo store.
*/

beforeEach(function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    Cache::flush();
});

it('lists only the active themes in the gallery', function (): void {
    Theme::query()->where('slug', 'elora-2')->update(['is_active' => false]);
    $active = Theme::query()->where('is_active', true)->get();

    $response = $this->get($this->centralUrl('/themes'))->assertOk();

    foreach ($active as $theme) {
        $response->assertSee('data-theme-card="'.$theme->slug.'"', false);
    }

    $response->assertDontSee('data-theme-card="elora-2"', false)
        ->assertSee(route('central.register', ['theme' => $active->first()->slug]), false);
});

it('renders a noindex live preview for every active theme', function (string $slug): void {
    $tenant = $this->createTenant('theme-demo');
    app(TenantRepositoryContract::class)->markPlatformDemoStore($tenant);

    $theme = Theme::query()->where('slug', $slug)->sole();

    $this->get($this->centralUrl('/themes/'.$slug.'?lang=ar'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('data-page="theme-preview"', false)
        ->assertSee('theme-demo.'.CentralDomains::primary(), false)
        ->assertSee('theme_preview='.$slug, false)
        ->assertSee('lang=ar', false)
        ->assertSee('data-tp-device="mobile"', false)
        ->assertSee(e(route('central.register', ['theme' => $slug])), false)
        ->assertSee(e($theme->getTranslation('name', 'ar')), false);
})->with(['ecommet', 'elora-1', 'elora-6', 'souqify-1', 'souqify-6']);

it('falls back to the screenshot without a demo store and 404s unknown or inactive themes', function (): void {
    $this->get($this->centralUrl('/themes/souqify-2'))->assertOk()->assertSee(__('content::theme_preview.preview.no_demo'));

    Theme::query()->where('slug', 'souqify-3')->update(['is_active' => false]);
    $this->get($this->centralUrl('/themes/souqify-3'))->assertNotFound();
    $this->get($this->centralUrl('/themes/no-such-theme'))->assertNotFound();
    $this->get($this->centralUrl('/preview/elora-1'))->assertRedirect('/themes/elora-1');
});

it('preselects the theme at registration and stores it on the registration', function (): void {
    Notification::fake();

    $this->get($this->centralUrl('/register?theme=elora-3'))
        ->assertOk()
        ->assertSee('data-preselected-theme="elora-3"', false);

    signUpAndChoose(paidPlan(trialDays: 14), 'trial')->assertRedirect();

    $theme = Theme::query()->where('slug', 'elora-3')->sole();
    expect(VendorRegistration::query()->latest('id')->first()->meta['theme_id'] ?? null)->toBe($theme->id);
});

it('renders the theme picked at registration until the store saves its own', function (): void {
    $theme = Theme::query()->where('slug', 'souqify-4')->sole();
    $tenant = $this->createTenant('picked', ['theme_id' => $theme->id]);

    $tenant->run(function () use ($theme): void {
        expect(app(StoreThemeService::class)->resolveBuiltIn(null)->slug)->toBe($theme->slug);
    });
});

it('keeps the demo store read-only and out of search engines', function (): void {
    $shop = shopSetup('demoro');
    app(TenantRepositoryContract::class)->markPlatformDemoStore($shop->tenant);
    expect($shop->tenant->fresh()->getAttribute(DemoStore::FLAG))->toBeTrue();

    $this->get($this->tenantUrl($shop->tenant, '/'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('data-demo-store-bar', false);

    $this->postJson($this->tenantUrl($shop->tenant, storeApi('cart/items')), ['store_product_id' => $shop->catalog->id, 'quantity' => 1])
        ->assertForbidden()
        ->assertJsonPath('message', __('content::theme_preview.demo.blocked'));

    $this->get($this->tenantUrl($shop->tenant, '/checkout'))
        ->assertRedirect()
        ->assertSessionHas('demo_store_notice', __('content::theme_preview.demo.blocked'));

    $this->post($this->tenantUrl($shop->tenant, '/account/login'), ['email' => 'a@b.test', 'password' => 'x'])
        ->assertRedirect()
        ->assertSessionHas('demo_store_notice');
});

it('leaves a normal store writable', function (): void {
    $shop = shopSetup('normalshop');

    $this->postJson($this->tenantUrl($shop->tenant, storeApi('cart/items')), ['store_product_id' => $shop->catalog->id, 'quantity' => 1])
        ->assertCreated();
    $this->get($this->tenantUrl($shop->tenant, '/'))->assertOk()->assertDontSee('data-demo-store-bar', false);
});

it('shows a banner without its link when the store does not sell the linked category (QA-FINAL-007)', function (): void {
    $sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $tenant = $this->createTenant('bannerlinks', ['selling_country_ids' => [$sa->id], 'default_country_id' => $sa->id]);
    Banner::factory()->create(['title' => ['en' => 'Gaming'], 'link_url' => '/category/demo-not-sold', 'sort_order' => 1]);
    Banner::factory()->create(['title' => ['en' => 'Offers'], 'link_url' => '/offers', 'sort_order' => 2]);

    $tenant->run(function () use ($sa): void {
        $banners = app(BannerCatalog::class)->forPlacement('home.hero', $sa->id, 'en');

        expect(array_column(array_map(static fn ($banner): array => $banner->toArray(), $banners), 'url', 'title'))
            ->toBe(['Gaming' => null, 'Offers' => '/offers']);
    });
});
