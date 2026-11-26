<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Support\EloraVariants;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Commerce/FulfilmentHelpers.php';

/*
| Elora home variants (SF-ELO-001 … 006) and shell (SF-ELO-010 / 011): one home view drives
| all six variants from EloraVariants; the page carries the variant for the tokens, the
| Elora navbar (mega menu + search autocomplete), footer, phone nav and product cards.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    $category = Category::factory()->create(['slug' => 'elora-home-gadgets', 'name' => 'Gadgets']);
    $this->shop->central->categories()->attach($category->id);
    $this->shop->tenant->run(fn () => $this->shop->own->forceFill(['name' => 'Desk Lamp', 'category_ids' => [$category->id]])->save());
    tenancy()->end();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
});

it('renders every Elora home variant from the variant config', function (int $variant): void {
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('elora-'.$variant));
    tenancy()->end();

    $this->withSession($this->sa)->get(($this->url)('/'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.home')
        ->assertViewHas('variant', $variant)
        ->assertSee('data-theme-variant="'.$variant.'"', false)
        ->assertSee('elo-card', false)
        ->assertSee('Desk Lamp')
        ->assertSee('data-rail', false)
        ->assertSee($variant === 1 ? 'data-elo-tabs' : 'data-marquee', false)
        ->assertSee('data-elo-menu-toggle', false)
        ->assertSee('elo-footer', false)
        ->assertSee($variant === 1 ? 'elo-mobile-nav' : 'elo-mobile-bar', false);
})->with(range(1, EloraVariants::COUNT));

it('keeps the Ecommet home for Ecommet stores', function (): void {
    $this->withSession($this->sa)->get(($this->url)('/'))
        ->assertOk()
        ->assertViewIs('storefront::themes.ecommet.pages.home')
        ->assertDontSee('elo-card', false);
});

it('gives every variant a section list that starts with the hero', function (): void {
    foreach (range(1, EloraVariants::COUNT) as $variant) {
        $sections = EloraVariants::sections($variant);

        expect($sections[0]['type'])->toBe('hero')
            ->and(array_column($sections, 'type'))->toContain('categories', 'flash', 'badges');
    }

    expect(EloraVariants::sections(99))->toBe(EloraVariants::sections(1));
});
