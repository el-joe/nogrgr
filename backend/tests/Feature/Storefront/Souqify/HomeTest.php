<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Support\SouqifyVariants;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Commerce/FulfilmentHelpers.php';

/*
| Souqify home variants (SF-SQF-001 … 006) and shell (SF-SQF-010 / 011): one home view drives
| all six variants from SouqifyVariants; the page carries the variant for the tokens, the
| Souqify navbar (category mega menu + scoped search with autocomplete), footer, phone tab
| bar and product cards, plus the store's badge shelves.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    $category = Category::factory()->create(['slug' => 'souqify-home-gadgets', 'name' => 'Gadgets']);
    $this->shop->central->categories()->attach($category->id);
    $this->category = $category;
    $this->shop->tenant->run(fn () => $this->shop->own->forceFill(['name' => 'Desk Lamp', 'category_ids' => [$category->id]])->save());
    tenancy()->end();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
});

it('renders every Souqify home variant from the variant config', function (int $variant): void {
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('souqify-'.$variant));
    tenancy()->end();

    $this->withSession($this->sa)->get(($this->url)('/'))
        ->assertOk()
        ->assertViewIs('storefront::themes.souqify.pages.home')
        ->assertViewHas('variant', $variant)
        ->assertSee('data-theme-variant="'.$variant.'"', false)
        ->assertSee('sq-pc', false)
        ->assertSee('Desk Lamp')
        ->assertSee('data-rail', false)
        ->assertSee('data-marquee', false)
        ->assertSee($variant === 1 ? 'sq-cats-squares' : 'sq-shop-', false)
        ->assertSee('data-sq-menu-toggle', false)
        ->assertSee('name="cat[]"', false)
        ->assertSee('sq-footer', false)
        ->assertSee('sq-tabbar', false)
        ->assertSee('sq-mobile-head', false)
        ->assertDontSee('elo-card', false)
        ->assertDontSee('eco-card', false);
})->with(range(1, SouqifyVariants::COUNT));

it('renders the Souqify home in Arabic', function (): void {
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('souqify-3'));
    tenancy()->end();

    $this->withSession($this->sa + ['locale' => 'ar'])->get(($this->url)('/?lang=ar'))
        ->assertOk()
        ->assertViewIs('storefront::themes.souqify.pages.home')
        ->assertSee('dir="rtl"', false)
        ->assertSee('sq-pc', false)
        ->assertSee('تسوق حسب القسم');
});

it('shows the store badge shelves on the Souqify home', function (): void {
    $badge = ProductBadge::factory()->create(['name' => ['en' => 'Hot Deal', 'ar' => 'عرض ساخن'], 'is_active' => true]);
    $this->shop->tenant->run(function () use ($badge): void {
        $this->shop->own->forceFill(['badge_ids' => [$badge->id]])->save();
        app(StoreThemeService::class)->activate('souqify-2');
    });
    tenancy()->end();

    $this->withSession($this->sa)->get(($this->url)('/'))
        ->assertOk()
        ->assertSee('aria-label="Hot Deal"', false)
        ->assertSee('sq-pc-tag', false);
});

it('uses the Souqify card on the shared inner listings', function (): void {
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('souqify-5'));
    tenancy()->end();

    $this->withSession($this->sa)->get(($this->url)('/new-in'))
        ->assertOk()
        ->assertSee('sq-pc', false)
        ->assertSee('data-add-to-cart', false)
        ->assertSee('Desk Lamp');
});

it('gives every variant a section list that starts with the hero and keeps the badge shelves', function (): void {
    foreach (range(1, SouqifyVariants::COUNT) as $variant) {
        $sections = SouqifyVariants::sections($variant);

        expect($sections[0]['type'])->toBe('hero')
            ->and(array_column($sections, 'type'))->toContain('features', 'categories', 'flash', 'badges', 'recommended');
    }

    expect(SouqifyVariants::sections(99))->toBe(SouqifyVariants::sections(1));
});
