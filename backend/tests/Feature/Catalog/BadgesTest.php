<?php

use App\Modules\Catalog\DTOs\BadgeAssignmentData;
use App\Modules\Catalog\Enums\BadgeAutoFill;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Jobs\RefreshBadgeAutoFill;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductBadgeAssignment;
use App\Modules\Catalog\Services\BadgeService;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Badges hub (ADM-017…017g): permissions, tabs, assign (search / whole category),
| remove, sort persistence, settings and auto-fill.
*/

function badgeIds(BadgeType $type): array
{
    $badge = ProductBadge::query()->where('type', $type->value)->sole();

    return ProductBadgeAssignment::query()->where('product_badge_id', $badge->id)->orderBy('sort_order')->pluck('product_id')->all();
}

it('redirects guests and forbids admins without badge permissions', function (): void {
    $product = Product::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/badges'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/catalog/badges'))->assertForbidden();

    $this->actingAs(catalogAdmin(['catalog.badges.view']), 'admin');
    $this->get($this->centralUrl('/admin/catalog/badges/best_selling'))->assertOk();
    $this->getJson($this->centralUrl('/admin/catalog/badges/best_selling/search'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/catalog/badges/best_selling/products'), ['product_ids' => [$product->id]])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/catalog/badges/best_selling/products/{$product->id}"))->assertForbidden();
    $this->putJson($this->centralUrl('/admin/catalog/badges/best_selling/order'), ['ids' => [$product->id]])->assertForbidden();
    $this->putJson($this->centralUrl('/admin/catalog/badges/best_selling'), [])->assertForbidden();
});

it('shows one tab per badge with counts and the empty state', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/badges'))
        ->assertOk()
        ->assertSee(__('catalog::enums.badge_type.new_in'))
        ->assertSee(__('catalog::enums.badge_type.trending_now'))
        ->assertSee(__('catalog::badges.empty_title'));

    expect(ProductBadge::query()->count())->toBe(count(BadgeType::cases()));

    $this->get($this->centralUrl('/admin/catalog/badges/unknown'))->assertNotFound();
});

it('searches products for the assign modal, leaving out assigned ones', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $watch = Product::factory()->create(['name' => ['en' => 'Smart Watch', 'ar' => 'ساعة ذكية'], 'sku' => 'NG-WATCH']);
    Product::factory()->create(['name' => ['en' => 'Linen Dress', 'ar' => 'فستان']]);

    $json = $this->getJson($this->centralUrl('/admin/catalog/badges/featured/search?q=watch'))->assertOk()->json();
    expect($json['data'])->toHaveCount(1)
        ->and($json['data'][0])->toMatchArray(['id' => $watch->id, 'name' => 'Smart Watch', 'sku' => 'NG-WATCH']);

    app(BadgeService::class)->assign(app(BadgeService::class)->badge(BadgeType::Featured), new BadgeAssignmentData([$watch->id]));

    expect($this->getJson($this->centralUrl('/admin/catalog/badges/featured/search?q=watch'))->json('data'))->toBe([])
        ->and($this->getJson($this->centralUrl('/admin/catalog/badges/featured/search'))->json('meta.total'))->toBe(1);
});

it('assigns picked products and a whole category with its sub-categories, skipping duplicates', function (): void {
    $this->actingAs(catalogAdmin(['catalog.badges.manage']), 'admin');
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();
    $picked = Product::factory()->create();
    $inParent = Product::factory()->inCategory($parent)->create();
    $inChild = Product::factory()->inCategory($child)->create();

    $this->postJson($this->centralUrl('/admin/catalog/badges/best_selling/products'), ['product_ids' => [$picked->id]])
        ->assertOk()->assertJson(['success' => true, 'data' => ['added' => 1]]);

    $this->postJson($this->centralUrl('/admin/catalog/badges/best_selling/products'), ['product_ids' => [$picked->id], 'category_id' => $parent->id])
        ->assertOk()->assertJson(['data' => ['added' => 2]]);

    expect(badgeIds(BadgeType::BestSelling))->toBe([$picked->id, $inParent->id, $inChild->id])
        ->and(badgeIds(BadgeType::NewIn))->toBe([]);

    $this->get($this->centralUrl('/admin/catalog/badges/best_selling'))->assertOk()->assertSee($inChild->name);
});

it('validates assignments', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/catalog/badges/new_in/products'), [])->assertJsonValidationErrors(['product_ids', 'category_id']);
    $this->postJson($this->centralUrl('/admin/catalog/badges/new_in/products'), ['category_id' => 999999])->assertJsonValidationErrors('category_id');
    $this->postJson($this->centralUrl('/admin/catalog/badges/new_in/products'), ['product_ids' => [999999]])
        ->assertUnprocessable()->assertJson(['message' => __('catalog::badges.nothing_to_add')]);
});

it('removes a product and persists the drag-and-drop order', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    [$a, $b, $c] = Product::factory()->count(3)->create()->all();
    $service = app(BadgeService::class);
    $service->assign($service->badge(BadgeType::Recommended), new BadgeAssignmentData([$a->id, $b->id, $c->id]));

    $this->putJson($this->centralUrl('/admin/catalog/badges/recommended/order'), ['ids' => [$c->id, $a->id, $b->id]])->assertOk();
    expect(badgeIds(BadgeType::Recommended))->toBe([$c->id, $a->id, $b->id]);

    $this->putJson($this->centralUrl('/admin/catalog/badges/recommended/order'), ['ids' => [$c->id, $a->id]])
        ->assertUnprocessable()->assertJson(['message' => __('catalog::badges.order_stale')]);

    $this->deleteJson($this->centralUrl("/admin/catalog/badges/recommended/products/{$a->id}"))->assertOk();
    expect(badgeIds(BadgeType::Recommended))->toBe([$c->id, $b->id]);

    $this->deleteJson($this->centralUrl("/admin/catalog/badges/recommended/products/{$a->id}"))->assertUnprocessable();
});

it('saves badge settings and refills automatic lists', function (): void {
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $old = Product::factory()->create(['published_at' => now()->subDays(5), 'sold_count' => 50]);
    $new = Product::factory()->create(['published_at' => now()->subDay(), 'sold_count' => 2]);

    $this->putJson($this->centralUrl('/admin/catalog/badges/trending_now'), [
        'name' => ['en' => 'Hot now', 'ar' => 'رائج'],
        'is_active' => '0',
        'auto_fill' => 'top_selling',
        'auto_fill_limit' => 1,
    ])->assertOk();

    $badge = ProductBadge::query()->where('type', 'trending_now')->sole();
    expect($badge->name)->toBe('Hot now')
        ->and($badge->is_active)->toBeFalse()
        ->and($badge->auto_fill)->toBe(BadgeAutoFill::TopSelling);
    Queue::assertPushed(RefreshBadgeAutoFill::class, fn (RefreshBadgeAutoFill $job): bool => $job->type === BadgeType::TrendingNow);

    (new RefreshBadgeAutoFill(BadgeType::TrendingNow))->handle(app(BadgeService::class));
    expect(badgeIds(BadgeType::TrendingNow))->toBe([$old->id]);

    $badge->update(['auto_fill' => BadgeAutoFill::Newest, 'auto_fill_limit' => 2]);
    (new RefreshBadgeAutoFill)->handle(app(BadgeService::class));
    expect(badgeIds(BadgeType::TrendingNow))->toBe([$new->id, $old->id]);

    $this->putJson($this->centralUrl('/admin/catalog/badges/trending_now'), ['name' => ['en' => ''], 'auto_fill' => 'sometimes', 'auto_fill_limit' => 0])
        ->assertJsonValidationErrors(['name.en', 'name.ar', 'is_active', 'auto_fill', 'auto_fill_limit']);
});
