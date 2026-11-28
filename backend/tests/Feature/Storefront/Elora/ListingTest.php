<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Support\Listing\FilterGroups;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Commerce/FulfilmentHelpers.php';

/*
| Elora category listing (Figma 1:17172 / m 1:21453 / filters m 1:22799): the "up one level"
| back target at every depth of the tree, the filter parameters and the filter group rules.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('elora-1'));
    $top = Category::factory()->create(['slug' => 'elp-top', 'name' => 'Home goods']);
    $mid = Category::factory()->create(['slug' => 'elp-mid', 'name' => 'Lighting', 'parent_id' => $top->id]);
    $leaf = Category::factory()->create(['slug' => 'elp-leaf', 'name' => 'Lamps', 'parent_id' => $mid->id]);
    $this->shop->central->categories()->attach($leaf->id);
    $this->shop->tenant->run(fn () => $this->shop->own->forceFill(['name' => 'Desk Lamp', 'category_ids' => [$leaf->id]])->save());
    tenancy()->end();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
    $this->page = fn (string $path) => $this->withSession($this->sa)->get(($this->url)($path))->assertOk();
});

it('goes back one level up the category tree, ending at all categories', function (string $slug, string $target): void {
    $html = ($this->page)('/category/'.$slug)->getContent();
    $href = preg_quote(($this->url)($target), '#');

    // Desktop tile before the circles and the phone search pill chevron share the target.
    expect($html)->toMatch('#<a href="'.$href.'" class="elp-circle elp-up[^"]*"#')
        ->toMatch('#<a href="'.$href.'" class="elp-search-back" data-elp-back#');
})->with([
    'leaf to its parent' => ['elp-leaf', '/category/elp-mid'],
    'middle to the top category' => ['elp-mid', '/category/elp-top'],
    'top category to all categories' => ['elp-top', '/category'],
]);

it('draws no back tile on the all-categories page', function (): void {
    ($this->page)('/category')->assertDontSee('elp-up', false);
});

it('filters by the quick pills, sale, availability and a price typed in the display currency', function (): void {
    ($this->page)('/category/elp-leaf?in_stock=1')->assertSee('Desk Lamp')->assertSee('aria-current="true"', false);
    ($this->page)('/category/elp-leaf?out_of_stock=1')->assertDontSee('Desk Lamp')->assertSee('elo-empty', false);
    ($this->page)('/category/elp-leaf?featured=1&rating=4')->assertSee('elo-empty', false);
    ($this->page)('/category/elp-leaf?max=0.01')->assertSee('elo-empty', false);
    ($this->page)('/category/elp-leaf?min=0.01&max=100000')->assertSee('Desk Lamp');
    ($this->page)('/category/elp-leaf?min=abc')->assertSee('Desk Lamp');
});

it('renders the filter sheet with the live count, reset and the cards fragment', function (): void {
    ($this->page)('/category/elp-leaf')
        ->assertSee('data-elp-sheet-form', false)
        ->assertSee('data-elp-count', false)
        ->assertSee('data-filter-reset', false)
        ->assertSee('data-elp-group="availability"', false)
        ->assertSee('data-elp-group="price"', false);

    $this->withSession($this->sa)->getJson(($this->url)('/category/elp-leaf?fragment=1'))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertSee('v1-card-grid');
});

it('orders the filter groups like the design and keeps selected values visible', function (): void {
    $attribute = static fn (int $id, string $name): array => ['id' => $id, 'name' => $name, 'values' => []];
    $groups = FilterGroups::order([$attribute(1, 'Color'), $attribute(2, 'Purity'), $attribute(3, 'Size'), $attribute(4, 'Brand')]);

    expect(array_column($groups, 'key'))->toBe(['brand', 'size', 'promo', 'color', 'price', 'rating', 'other', 'availability']);

    $values = array_map(static fn (int $i): array => ['value' => 'v'.$i, 'count' => 20 - $i], range(1, 12));
    $rows = FilterGroups::rows($values, ['v11']);

    expect(array_column($rows, 'more'))->toBe([false, false, false, false, false, false, false, false, true, true, false, true])
        ->and($rows[10]['checked'])->toBeTrue();
});
