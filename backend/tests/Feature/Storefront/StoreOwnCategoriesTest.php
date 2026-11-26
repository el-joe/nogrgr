<?php

use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\StoreBadgePosition;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreCategoryPosition;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\Catalog\BadgeShelves;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Support\StoreOwnCategory;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Store-own categories and badge order on the storefront (decision #74 / #79): every family
| lists the store's own categories next to the central ones, category pages show the
| products placed in them in the store's category order, and badge shelves / card tags use
| the store's badge order.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
});

it('lists a store-own category and shows its products in the store order', function (): void {
    $category = $this->shop->tenant->run(function () {
        $own = StoreCategory::factory()->create(['name' => ['en' => 'Summer Picks', 'ar' => 'مختارات الصيف'], 'slug' => 'summer-picks']);
        $this->shop->own->forceFill(['name' => 'Beach Lamp', 'store_category_ids' => [$own->id]])->save();
        $second = StoreProduct::factory()->create(['name' => 'Sun Hat', 'price' => Money::of('9.00'), 'stock' => 4, 'store_category_ids' => [$own->id]]);
        StoreCategoryPosition::query()->create(['category_ref' => 'store:'.$own->id, 'store_product_id' => $second->id, 'position' => 0]);
        StoreCategoryPosition::query()->create(['category_ref' => 'store:'.$own->id, 'store_product_id' => $this->shop->own->id, 'position' => 1]);

        return $own;
    });
    tenancy()->end();

    $this->withSession($this->sa)->get(($this->url)('/categories'))
        ->assertOk()
        ->assertSee('Summer Picks');

    $this->withSession($this->sa)->get(($this->url)('/category/summer-picks'))
        ->assertOk()
        ->assertSeeInOrder(['Sun Hat', 'Beach Lamp']);

    $this->shop->tenant->run(function () use ($category): void {
        $node = app(CategoryCatalog::class)->findBySlug('summer-picks');

        expect($node)->toBeInstanceOf(StoreOwnCategory::class)
            ->and($node->id)->toBe(-$category->id)
            ->and($node->storeCategoryId())->toBe($category->id);
    });
});

it('hides inactive store categories and ones limited to other countries', function (): void {
    $this->shop->tenant->run(function (): void {
        $off = StoreCategory::factory()->inactive()->create(['slug' => 'hidden-picks']);
        $elsewhere = StoreCategory::factory()->create(['slug' => 'uae-picks', 'country_ids' => [$this->shop->ae->id]]);
        $this->shop->own->forceFill(['store_category_ids' => [$off->id, $elsewhere->id]])->save();
    });
    tenancy()->end();

    $this->withSession($this->sa)->get(($this->url)('/category/hidden-picks'))->assertNotFound();
    $this->withSession($this->sa)->get(($this->url)('/category/uae-picks'))->assertNotFound();
});

it('orders badge shelves by the store badge order and tags cards with the badge', function (): void {
    $badge = ProductBadge::factory()->create(['name' => ['en' => 'Hot Deal', 'ar' => 'عرض ساخن'], 'is_active' => true]);

    $this->shop->tenant->run(function () use ($badge): void {
        $this->shop->own->forceFill(['name' => 'Beach Lamp', 'badge_ids' => [$badge->id]])->save();
        $second = StoreProduct::factory()->create(['name' => 'Sun Hat', 'price' => Money::of('9.00'), 'stock' => 4, 'badge_ids' => [$badge->id]]);
        StoreBadgePosition::query()->create(['badge_id' => $badge->id, 'store_product_id' => $second->id, 'position' => 0]);
        StoreBadgePosition::query()->create(['badge_id' => $badge->id, 'store_product_id' => $this->shop->own->id, 'position' => 1]);

        $shelves = app(BadgeShelves::class)->shelves();

        expect($shelves)->toHaveCount(1)
            ->and($shelves[0]['name'])->toBe('Hot Deal')
            ->and(array_map(fn ($card) => $card->name, $shelves[0]['cards']))->toBe(['Sun Hat', 'Beach Lamp'])
            ->and($shelves[0]['cards'][0]->badge)->toBe('Hot Deal');
    });
});
