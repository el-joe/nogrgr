<?php

use App\Modules\Catalog\DTOs\BadgeAssignmentData;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Events\BadgesChanged;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Services\BadgeService;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyService;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Listeners\ForgetProductIndexOnCatalogChange;
use App\Modules\Storefront\Services\StoreThemeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Product card ribbons come from the badge records (product_badges + assignments, store
| placement): name per language, active switch, platform badge order, visible at once
| after an admin or vendor change; a product without a badge shows none.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    $category = Category::factory()->create(['slug' => 'badge-gadgets', 'name' => 'Gadgets']);
    $this->shop->central->categories()->attach($category->id);
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('elora-1'));
    tenancy()->end();
    $this->badges = app(BadgeService::class);
    $this->badge = fn (BadgeType $type) => $this->badges->badge($type);
    $this->assign = fn (BadgeType $type) => $this->badges->assign($this->badges->badge($type), new BadgeAssignmentData([$this->shop->central->id]));
    // Each request starts clean, as in production (scoped catalog services keep per-request memos).
    $this->home = function (string $query = '') {
        app()->forgetScopedInstances();

        return $this->withSession(['storefront.country_id' => $this->shop->sa->id])->get($this->tenantUrl($this->shop->tenant, '/'.$query))->assertOk();
    };
});

it('shows no ribbon on a product without a badge', function (): void {
    ($this->home)()->assertSee('v1-card', false)->assertDontSee('v1-card-badge', false);
});

it('shows the badge record on the card and follows its changes at once', function (): void {
    ($this->home)()->assertDontSee('v1-card-badge', false);

    // The index is cached now: the assignment must still show on the next request.
    ($this->assign)(BadgeType::BestSelling);
    ($this->home)()->assertSee('data-badge="best_selling"', false)->assertSee('Best selling');

    ($this->badge)(BadgeType::BestSelling)->update(['name' => ['en' => 'Top pick', 'ar' => 'اختيار مميز']]);
    ($this->home)()->assertSee('title="Top pick">Top pick</span>', false);
    ($this->home)('?lang=ar')->assertSee('اختيار مميز');

    ($this->badge)(BadgeType::BestSelling)->update(['is_active' => false]);
    ($this->home)()->assertDontSee('v1-card-badge', false);
});

it('shows the first active badge in the platform badge order', function (): void {
    ($this->assign)(BadgeType::TrendingNow);
    ($this->assign)(BadgeType::NewIn);

    ($this->home)()->assertSee('data-badge="new_in"', false)->assertDontSee('data-badge="trending_now"', false);

    ($this->badge)(BadgeType::NewIn)->update(['sort_order' => 99]);
    ($this->home)()->assertSee('data-badge="trending_now"', false)->assertDontSee('data-badge="new_in"', false);
});

it('shows a badge the vendor places on a product and tells the storefront to rebuild', function (): void {
    $badge = ($this->badge)(BadgeType::Featured);
    Event::fake([BadgesChanged::class]);

    $this->shop->tenant->run(fn () => app(StoreTaxonomyService::class)->addToBadge($badge, [$this->shop->own->id]));
    tenancy()->end();

    // The rebuild itself is checked below: the test cache is per tenancy boot, production's is shared.
    Event::assertDispatched(BadgesChanged::class, fn (BadgesChanged $event): bool => $event->tenantId === (int) $this->shop->tenant->id);
    ($this->home)()->assertSee('data-badge="featured"', false);
});

it('bumps the product index of the store (or of every store) when badges change', function (): void {
    $cache = app('cache')->store();
    $listener = new ForgetProductIndexOnCatalogChange;

    $listener->handle(new BadgesChanged(7));
    $listener->handle(new BadgesChanged);

    expect((int) $cache->get('sf:index:ver:7'))->toBe(1)
        ->and((int) $cache->get('sf:index:ver:all'))->toBeGreaterThanOrEqual(1);
});

it('reads the badge rows once per page however many cards show them', function (): void {
    ($this->assign)(BadgeType::BestSelling);
    ($this->home)();

    $connection = DB::connection((new ProductBadge)->getConnectionName());
    $connection->flushQueryLog();
    $connection->enableQueryLog();
    ($this->home)();
    $reads = array_filter($connection->getQueryLog(), static fn (array $query): bool => preg_match('/from [`"]product_badges[`"] order by/i', $query['query']) === 1);
    $connection->disableQueryLog();

    expect(count($reads))->toBeLessThanOrEqual(2);
});
