<?php

use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\StoreBadgePosition;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreCategoryPosition;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Models\StoreLayout;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\Layouts\SectionProductResolver;
use App\Modules\Storefront\Services\Layouts\StoreLayoutPreviewService;
use App\Modules\Storefront\Services\Layouts\StoreLayoutService;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';
require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| VEN-032 page builder (decision #117 b): permissions + tenant isolation, draft vs published
| home rendering (web + signed draft preview + Store API), show / hide per country, version
| history + restore, discard / reset to the theme default, product source resolution
| (category / badge / manual golden lists) and section-based custom store pages.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    [$this->hat, $this->ball] = $this->shop->tenant->run(function (): array {
        app(StoreThemeService::class)->activate('elora-1');
        $this->shop->own->forceFill(['name' => 'Desk Lamp'])->save();

        return [
            StoreProduct::factory()->create(['name' => 'Sun Hat', 'price' => Money::of('9.00'), 'stock' => 4]),
            StoreProduct::factory()->create(['name' => 'Beach Ball', 'price' => Money::of('7.00'), 'stock' => 6]),
        ];
    });
    tenancy()->end();
    $this->owner = $this->createVendorOwner($this->shop->tenant);
    $this->admin = fn (string $path = ''): string => $this->tenantUrl($this->shop->tenant, 'admin/store/page-builder'.$path);
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
    $this->ae = ['storefront.country_id' => $this->shop->ae->id];
    // Home draft payload: every Elora v1 section in library order, with overrides.
    $this->home = function (array $overrides = []): array {
        $keys = ['hero', 'features', 'tabs', 'categories', 'flash', 'rail.trending', 'category-band', 'rail.best', 'rail.newRail', 'shop-categories', 'rail.recommended', 'badges', 'promo-rail'];

        return ['sections' => array_map(static fn (string $key): array => array_replace_recursive(['key' => $key, 'enabled' => 1, 'settings' => [], 'countries' => []], $overrides[$key] ?? []), $keys)];
    };
    $this->layout = fn (string $slot = 'home'): ?StoreLayout => $this->shop->tenant->run(fn () => StoreLayout::query()->where('slot', $slot)->first());
});

it('sends guests to the login and guards every endpoint with store.design permissions', function (): void {
    $this->get(($this->admin)())->assertRedirect($this->tenantUrl($this->shop->tenant, 'login'));

    [$none, $viewer] = $this->shop->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.design.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->shop->tenant);
    $this->getJson(($this->admin)())->assertForbidden();
    $this->getJson(($this->admin)('/home-default/preview'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->shop->tenant);
    $this->get(($this->admin)())->assertOk()
        ->assertSee('data-page="vendor-page-builder"', false)
        ->assertSee(__('content::page_builder.readonly_note'))
        ->assertDontSee('data-drag-handle', false);
    $this->getJson(($this->admin)('/home-default/versions'))->assertOk();
    $this->putJson(($this->admin)('/home-default/draft'), ($this->home)())->assertForbidden();
    $this->postJson(($this->admin)('/home-default/publish'))->assertForbidden();
    $this->postJson(($this->admin)('/home-default/reset'))->assertForbidden();
    $this->deleteJson(($this->admin)('/home-default/draft'))->assertForbidden();
    $this->getJson(($this->admin)('/products'))->assertForbidden();
    $this->postJson(($this->admin)('/images'))->assertForbidden();

    expect(($this->layout)())->toBeNull();
});

it('keeps stores apart: another store owner is not signed in here and unknown targets are 404', function (): void {
    $other = $this->createTenant('pb-other-'.strtolower(str()->random(5)), ['selling_country_ids' => [$this->shop->sa->id]]);
    $otherOwner = $this->createVendorOwner($other);
    $page = $this->shop->tenant->run(fn () => StorePage::query()->create(['slug' => 'lookbook', 'type' => StorePageType::Custom, 'title' => ['en' => 'Lookbook'], 'is_active' => true]));

    $this->actingAsVendor($otherOwner, $other);
    // Signed in on the other store only: this store's builder never opens or saves.
    expect($this->getJson(($this->admin)())->status())->toBeIn([401, 403, 404]);
    expect($this->putJson(($this->admin)('/home-default/draft'), ($this->home)())->status())->toBeIn([401, 403, 404, 419]);
    // The other store has no page with that id.
    $this->get($this->tenantUrl($other, 'admin/store/page-builder/pages/'.$page->id))->assertNotFound();
    $this->putJson($this->tenantUrl($other, 'admin/store/page-builder/page-'.$page->id.'/draft'), ['sections' => []])->assertNotFound();
    $this->putJson($this->tenantUrl($other, 'admin/store/page-builder/home-'.$this->shop->ae->id.'/draft'), ($this->home)())->assertNotFound();

    expect(($this->layout)())->toBeNull();
});

it('renders the draft only in the signed preview and the published layout on the storefront and Store API', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $this->get(($this->admin)())->assertOk()
        ->assertSee(__('content::page_builder.never_published'))
        ->assertSee('data-section-item data-key="rail.trending"', false)
        ->assertSee(__('content::page_builder.sources.manual'));

    $this->putJson(($this->admin)('/home-default/draft'), ($this->home)([
        'rail.trending' => ['settings' => ['title' => ['en' => 'Picked for summer', 'ar' => 'مختارات الصيف'], 'subtitle' => ['en' => 'Hand-picked'], 'source' => 'manual', 'products' => [$this->ball->id, $this->hat->id]]],
        'badges' => ['enabled' => 0],
    ]))->assertOk()->assertJsonPath('data.state', 'draft');

    // Published storefront: theme default until publish.
    $this->withSession($this->sa)->get(($this->url)('/'))->assertOk()->assertDontSee('Picked for summer');

    // Signed draft preview.
    $preview = $this->getJson(($this->admin)('/home-default/preview'))->assertOk()->json('data.url');
    expect($preview)->toContain('lp_signature=');
    $this->get($preview)->assertOk()->assertSee('Picked for summer')->assertSee('Hand-picked')->assertSeeInOrder(['Picked for summer', 'Beach Ball', 'Sun Hat']);
    // A forged signature shows the published layout.
    $this->get(preg_replace('/lp_signature=[a-f0-9]+/', 'lp_signature='.str_repeat('0', 64), $preview))->assertOk()->assertDontSee('Picked for summer');

    $this->postJson(($this->admin)('/home-default/publish'))->assertOk()->assertJsonPath('message', __('content::page_builder.published', ['number' => 1]));
    $layout = ($this->layout)();
    expect($layout->draft)->toBeNull()
        ->and($layout->published[5]['settings']['products'])->toBe([$this->ball->id, $this->hat->id])
        ->and($layout->published[11])->toMatchArray(['key' => 'badges', 'enabled' => false]);

    $this->withSession($this->sa)->get(($this->url)('/'))->assertOk()->assertSeeInOrder(['Picked for summer', 'Beach Ball', 'Sun Hat']);

    $api = $this->getJson(storeApi('home'), shopHeaders($this->shop->tenant))->assertOk();
    expect($api->json('data.layout'))->toContain('rail.trending')->not->toContain('badges');
    $trending = collect($api->json('data.layout_sections'))->firstWhere('key', 'rail.trending');
    expect($trending['title'])->toBe('Picked for summer')
        ->and(array_column($trending['products'], 'name'))->toBe(['Beach Ball', 'Sun Hat']);
});

it('hides a section in the countries it is not shown in', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $publish = function (array $countries): void {
        $this->putJson(($this->admin)('/home-default/draft'), ($this->home)([
            'rail.newRail' => ['settings' => ['title' => ['en' => 'Country rail']], 'countries' => $countries],
        ]))->assertOk();
        $this->postJson(($this->admin)('/home-default/publish'))->assertOk();
    };

    // Only in the UAE (unknown ids dropped): Saudi shoppers do not get it.
    $publish([$this->shop->ae->id, 999999]);
    expect(($this->layout)()->published[8]['countries'])->toBe([$this->shop->ae->id]);
    $this->withSession($this->sa)->get(($this->url)('/'))->assertOk()->assertSee('data-country="sa"', false)->assertDontSee('Country rail');
    expect(collect($this->getJson(storeApi('home'), shopHeaders($this->shop->tenant) + ['X-Country' => 'SA'])->json('data.layout'))->contains('rail.newRail'))->toBeFalse();

    // Saudi Arabia added: shown there.
    $publish([$this->shop->ae->id, $this->shop->sa->id]);
    $this->withSession($this->sa)->get(($this->url)('/'))->assertOk()->assertSee('Country rail');
});

it('keeps every publish as a version, restores one into the draft, discards and resets', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $title = fn (string $text): array => ($this->home)(['rail.newRail' => ['settings' => ['title' => ['en' => $text]]]]);

    $this->putJson(($this->admin)('/home-default/draft'), $title('First title'))->assertOk();
    $this->postJson(($this->admin)('/home-default/publish'))->assertOk();
    $this->putJson(($this->admin)('/home-default/draft'), $title('Second title'))->assertOk();
    $this->postJson(($this->admin)('/home-default/publish'))->assertOk();

    $versions = $this->shop->tenant->run(fn () => ($this->layout)()->versions()->get());
    expect($versions->pluck('number')->all())->toBe([2, 1])
        ->and($versions->last()->published_by_name)->toBe($this->owner->name);
    $this->getJson(($this->admin)('/home-default/versions'))->assertOk()->assertSee('v1')->assertSee('v2');

    $this->postJson(($this->admin)('/home-default/versions/'.$versions->last()->id.'/restore'))->assertOk()
        ->assertJsonPath('message', __('content::page_builder.restored', ['number' => 1]));
    expect(($this->layout)()->draft[8]['settings']['title'])->toBe(['en' => 'First title']);
    // Still the published v2 until publish.
    $this->withSession($this->sa)->get(($this->url)('/'))->assertSee('Second title')->assertDontSee('First title');
    $this->get(($this->admin)())->assertSee(__('content::page_builder.state.draft'))->assertSee(__('content::page_builder.discard'));

    $this->deleteJson(($this->admin)('/home-default/draft'))->assertOk();
    expect(($this->layout)()->draft)->toBeNull();

    $this->postJson(($this->admin)('/home-default/reset'))->assertOk();
    expect(($this->layout)()->draft[8])->toBe(['key' => 'rail.newRail', 'enabled' => true, 'settings' => [], 'countries' => []]);
    $this->postJson(($this->admin)('/home-default/publish'))->assertOk();
    $this->withSession($this->sa)->get(($this->url)('/'))->assertDontSee('Second title');

    // Unknown version of this layout.
    $this->postJson(($this->admin)('/home-default/versions/999999/restore'))->assertNotFound();
});

it('falls back to the theme default when the store switches to another theme', function (): void {
    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $this->putJson(($this->admin)('/home-default/draft'), ($this->home)(['rail.newRail' => ['settings' => ['title' => ['en' => 'Elora only title']]]]))->assertOk();
    $this->postJson(($this->admin)('/home-default/publish'))->assertOk();
    $this->withSession($this->sa)->get(($this->url)('/'))->assertSee('Elora only title');

    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('souqify-1'));
    tenancy()->end();
    $this->app->forgetScopedInstances();

    $this->withSession($this->sa)->get(($this->url)('/'))->assertOk()->assertDontSee('Elora only title');
    $this->get(($this->admin)())->assertOk()->assertSee('data-section-item data-key="rail.trending"', false)
        ->assertSee(__('content::page_builder.state.default'));
});

it('resolves product sources to golden lists (category / badge / manual / newest)', function (): void {
    $lists = $this->shop->tenant->run(function (): array {
        $category = StoreCategory::factory()->create(['name' => ['en' => 'Summer', 'ar' => 'الصيف'], 'slug' => 'pb-summer']);
        $badge = ProductBadge::factory()->create(['name' => ['en' => 'Hot', 'ar' => 'ساخن'], 'is_active' => true]);
        foreach ([$this->hat, $this->ball, $this->shop->own] as $product) {
            $product->forceFill(['store_category_ids' => [$category->id], 'badge_ids' => [$badge->id]])->save();
        }
        StoreCategoryPosition::query()->create(['category_ref' => 'store:'.$category->id, 'store_product_id' => $this->ball->id, 'position' => 0]);
        StoreCategoryPosition::query()->create(['category_ref' => 'store:'.$category->id, 'store_product_id' => $this->shop->own->id, 'position' => 1]);
        StoreBadgePosition::query()->create(['badge_id' => $badge->id, 'store_product_id' => $this->shop->own->id, 'position' => 0]);
        StoreBadgePosition::query()->create(['badge_id' => $badge->id, 'store_product_id' => $this->hat->id, 'position' => 1]);
        request()->setLaravelSession(app('session')->driver());
        request()->session()->put('storefront.country_id', $this->shop->sa->id);
        $resolver = app(SectionProductResolver::class);
        $names = fn (array $settings): array => array_map(static fn ($card): string => $card->name, $resolver->cards($settings, 'trending') ?? []);

        return [
            'category' => $names(['source' => 'category', 'category' => 'store:'.$category->id]),
            'badge' => $names(['source' => 'badge', 'badge' => $badge->id, 'limit' => 2]),
            'manual' => $names(['source' => 'manual', 'products' => [$this->hat->id, 999999, $this->shop->own->id]]),
            'missing' => $names(['source' => 'category', 'category' => 'store:999999']),
            'default' => $resolver->cards([], 'trending'),
        ];
    });

    expect($lists['category'])->toBe(['Beach Ball', 'Desk Lamp', 'Sun Hat'])
        ->and($lists['badge'])->toBe(['Desk Lamp', 'Sun Hat'])
        ->and($lists['manual'])->toBe(['Sun Hat', 'Desk Lamp'])
        ->and($lists['missing'])->toBe([])
        ->and($lists['default'])->toBeNull();
});

it('normalises settings against the section schema', function (): void {
    $items = $this->shop->tenant->run(function (): array {
        $service = app(StoreLayoutService::class);
        $target = $service->homeTarget('default');

        return $service->normalise($target, [
            ['key' => 'rail.trending', 'enabled' => '1', 'settings' => ['title' => ['en' => '<b>Hi</b>', 'xx' => 'nope'], 'limit' => 500, 'source' => 'manual', 'products' => [], 'style' => 'zzz', 'evil' => 'x']],
            ['key' => 'unknown.section', 'enabled' => '1'],
            ['key' => 'hero', 'enabled' => '0', 'settings' => ['banners' => ['vendor:3', 'javascript:1'], 'style' => 'full']],
        ]);
    });

    expect($items[0])->toBe(['key' => 'rail.trending', 'enabled' => true, 'settings' => ['title' => ['en' => 'Hi'], 'limit' => 24], 'countries' => []])
        ->and($items[1])->toBe(['key' => 'hero', 'enabled' => false, 'settings' => ['banners' => ['vendor:3'], 'style' => 'full'], 'countries' => []])
        ->and(array_column($items, 'key'))->not->toContain('unknown.section')
        ->and(count($items))->toBe(13);
});

it('builds a custom store page from sections and renders them after publish', function (): void {
    $page = $this->shop->tenant->run(fn () => StorePage::query()->create(['slug' => 'lookbook', 'type' => StorePageType::Custom, 'title' => ['en' => 'Lookbook', 'ar' => 'الكتالوج'], 'body' => ['en' => '<p>Intro</p>'], 'is_active' => true]));
    $system = $this->shop->tenant->run(fn () => StorePage::query()->create(['slug' => 'terms-x', 'type' => StorePageType::System, 'title' => ['en' => 'Terms'], 'is_active' => true]));
    tenancy()->end();
    $this->actingAsVendor($this->owner, $this->shop->tenant);

    $this->get(($this->admin)('/pages/'.$system->id))->assertNotFound();
    $this->get(($this->admin)('/pages/'.$page->id))->assertOk()
        ->assertSee(__('content::page_builder.page_empty_title'))
        ->assertSee('data-section-template="faq"', false);

    $this->putJson(($this->admin)('/page-'.$page->id.'/draft'), ['sections' => [
        ['key' => 'faq1abcd', 'type' => 'faq', 'enabled' => 1, 'settings' => ['title' => ['en' => 'Lookbook questions'], 'items' => [['q' => ['en' => 'Do you ship abroad?'], 'a' => ['en' => 'Yes, to the Gulf.']], ['q' => ['en' => ''], 'a' => ['en' => 'dropped']]]]],
        ['key' => 'rail1abc', 'type' => 'product_rail', 'enabled' => 1, 'settings' => ['title' => ['en' => 'Shop the look'], 'source' => 'manual', 'products' => [$this->hat->id]]],
        ['key' => 'text1abc', 'type' => 'rich_text', 'enabled' => 1, 'settings' => ['body' => ['en' => '<p>Story<script>alert(1)</script></p>']]],
        ['key' => 'nope', 'type' => 'video', 'enabled' => 1],
    ]])->assertOk();

    $this->get(($this->url)('/pages/lookbook'))->assertOk()->assertDontSee('Lookbook questions');
    $this->postJson(($this->admin)('/page-'.$page->id.'/publish'))->assertOk();

    $published = ($this->layout)('page:'.$page->id)->published;
    expect(array_column($published, 'type'))->toBe(['faq', 'product_rail', 'rich_text'])
        ->and($published[0]['settings']['items'])->toHaveCount(1)
        ->and($published[2]['settings']['body']['en'])->not->toContain('script');

    $this->withSession($this->sa)->get(($this->url)('/pages/lookbook'))->assertOk()
        ->assertSee('data-page-sections', false)
        ->assertSeeInOrder(['Intro', 'Lookbook questions', 'Do you ship abroad?', 'Shop the look', 'Sun Hat', 'Story']);

    $api = $this->getJson(storeApi('pages/lookbook'), shopHeaders($this->shop->tenant))->assertOk();
    expect(array_column($api->json('data.sections'), 'type'))->toBe(['faq', 'product_rail', 'rich_text'])
        ->and($api->json('data.sections.1.products.0.name'))->toBe('Sun Hat');
});

it('signs previews per store and expires them', function (): void {
    $layout = $this->shop->tenant->run(function () {
        $service = app(StoreLayoutService::class);

        return $service->saveDraft($service->homeTarget('default'), ($this->home)()['sections']);
    });
    $url = $this->shop->tenant->run(fn () => app(StoreLayoutPreviewService::class)->url($this->shop->tenant, $layout));

    $this->travel(31)->minutes();
    $this->shop->tenant->run(function () use ($url): void {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect(app(StoreLayoutPreviewService::class)->current(request()->duplicate($query)))->toBeNull();
    });
});
