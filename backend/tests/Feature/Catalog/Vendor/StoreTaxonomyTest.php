<?php

use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductBadgeAssignment;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreCentralCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyReader;
use App\Modules\Catalog\Support\StoreCategoryRef;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| Store categories and badges (VEN-017 / 017a / 017b / 017c, VEN-018 / 018a / 018b,
| decision #74): central categories are hidden / ordered but never copied, store-own
| categories live in the tenant DB, product placement and order are store data.
*/

beforeEach(function (): void {
    Storage::fake('public');
    [$this->tenant, $this->owner] = catalogStore('taxo');
    $this->electronics = Category::factory()->create(['name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات']]);
    $this->fashion = Category::factory()->create(['name' => ['en' => 'Fashion', 'ar' => 'أزياء']]);
    $this->watch = Product::factory()->inCategory($this->electronics)->create(['name' => ['en' => 'Sports Smart Watch', 'ar' => 'ساعة']]);
    $this->dress = Product::factory()->inCategory($this->fashion)->create(['name' => ['en' => 'Linen Summer Dress', 'ar' => 'فستان']]);
    $this->watchRow = storeProduct($this->tenant, $this->watch->id, ['sort_order' => 1]);
    $this->dressRow = storeProduct($this->tenant, $this->dress->id, ['sort_order' => 2]);
    $this->own = $this->tenant->run(fn (): StoreProduct => StoreProduct::factory()->create(['name' => ['en' => 'Tote Bag', 'ar' => 'حقيبة'], 'sort_order' => 3]));
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/catalog'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->members = fn (StoreCategoryRef $ref): array => $this->tenant->run(fn (): array => app(StoreTaxonomyReader::class)->productIds($ref));
});

it('guards every taxonomy route by login and permission', function (): void {
    $this->get(($this->url)('/categories'))->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->get(($this->url)('/badges'))->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $viewer = catalogStaff($this->tenant, ['catalog.categories.view', 'catalog.badges.view']);
    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)('/categories'))->assertOk()->assertDontSee(__('catalog::taxonomy.categories.add'));
    $this->get(($this->url)('/badges'))->assertOk()->assertDontSee(__('catalog::taxonomy.badges.add_products'));
    $this->get(($this->url)('/categories/create'))->assertForbidden();
    $this->postJson(($this->url)('/categories'), ['name' => ['en' => 'X']])->assertForbidden();
    $this->patchJson(($this->url)("/categories/central-{$this->electronics->id}/active"), ['is_active' => 0])->assertForbidden();
    $this->postJson(($this->url)('/categories/sort'), ['ids' => []])->assertForbidden();
    $this->postJson(($this->url)("/categories/central-{$this->electronics->id}/products"), ['product_ids' => [$this->own->id]])->assertForbidden();

    $nobody = catalogStaff($this->tenant, []);
    $this->actingAsVendor($nobody, $this->tenant);
    $this->get(($this->url)('/categories'))->assertForbidden();
    $this->get(($this->url)('/badges'))->assertForbidden();
    $this->get(($this->url)('/categories/sort'))->assertForbidden();
});

it('lists platform and store categories with their products, and creates a store category', function (): void {
    ($this->as)();

    $this->get(($this->url)('/categories'))->assertOk()
        ->assertSee('Electronics')->assertSee('Fashion')->assertSee('Sports Smart Watch')
        ->assertSee(__('catalog::taxonomy.source.central'));

    $this->get(($this->url)('/categories/create'))->assertOk()->assertSee(__('catalog::taxonomy.categories.create_title'));

    $this->post(($this->url)('/categories'), ['name' => ['en' => ''], 'all_countries' => 1], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('name.en');
    $this->postJson(($this->url)('/categories'), ['name' => ['en' => 'Gifts'], 'country_ids' => [999999]])->assertJsonValidationErrors('country_ids.0');

    $this->post(($this->url)('/categories'), [
        'name' => ['en' => 'Gifts', 'ar' => 'هدايا'],
        'description' => ['en' => 'Gift ideas'],
        'all_countries' => 1,
        'is_active' => 1,
        'image' => UploadedFile::fake()->image('gifts.jpg', 400, 400),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('success', true);

    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::query()->sole());
    expect($gifts->slug)->toBe('gifts')
        ->and($gifts->getTranslation('name', 'ar'))->toBe('هدايا')
        ->and($gifts->country_ids)->toBeNull()
        ->and($gifts->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($gifts->image_path);

    // Central categories are never copied into the store (decision #65 / #74).
    $this->tenant->run(fn () => expect(StoreCategory::query()->count())->toBe(1));

    $this->get(($this->url)('/categories'))->assertOk()->assertSee('Gifts')->assertSee(__('catalog::taxonomy.source.store'));
    $this->get(($this->url)("/categories/{$gifts->id}/edit"))->assertOk()->assertSee('Gifts');

    $this->putJson(($this->url)("/categories/{$gifts->id}"), ['name' => ['en' => 'Gift ideas'], 'all_countries' => 1, 'is_active' => 1, 'is_featured' => 1, 'remove_image' => 1])->assertOk();
    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::query()->sole());
    expect($gifts->is_featured)->toBeTrue()->and($gifts->image_path)->toBeNull();

    // A category cannot be its own parent.
    $this->putJson(($this->url)("/categories/{$gifts->id}"), ['name' => ['en' => 'Gift ideas'], 'all_countries' => 1, 'parent_id' => $gifts->id])->assertUnprocessable();
});

it('hides a platform category for the store only and adds, removes and orders its products', function (): void {
    ($this->as)();
    $electronics = StoreCategoryRef::central($this->electronics->id);
    $key = $electronics->routeKey();

    $this->patchJson(($this->url)("/categories/{$key}/active"), ['is_active' => 0])->assertOk();
    $this->tenant->run(fn () => expect(StoreCentralCategory::query()->where('central_category_id', $this->electronics->id)->value('is_hidden'))->toBeTrue());
    $this->get(($this->url)('/categories'))->assertSee(__('catalog::taxonomy.categories.hidden_badge'));

    $this->get(($this->url)("/categories/{$key}/products"))->assertOk()->assertSee('Sports Smart Watch')->assertDontSee('Linen Summer Dress');

    $this->postJson(($this->url)("/categories/{$key}/products"), ['product_ids' => []])->assertJsonValidationErrors('product_ids');
    $this->postJson(($this->url)("/categories/{$key}/products"), ['product_ids' => [$this->dressRow->id, $this->own->id]])->assertOk();
    expect(($this->members)($electronics))->toBe([$this->watchRow->id, $this->dressRow->id, $this->own->id]);

    // The dress keeps its central Fashion placement (inherited ids are written once).
    expect($this->tenant->run(fn () => StoreProduct::query()->find($this->dressRow->id)->category_ids))->toBe([$this->fashion->id, $this->electronics->id]);

    $this->postJson(($this->url)("/categories/{$key}/products/sort"), ['ids' => [$this->own->id, $this->watchRow->id]])->assertUnprocessable();
    $this->postJson(($this->url)("/categories/{$key}/products/sort"), ['ids' => [$this->own->id, $this->watchRow->id, $this->dressRow->id]])->assertOk();
    expect(($this->members)($electronics))->toBe([$this->own->id, $this->watchRow->id, $this->dressRow->id]);
    $this->get(($this->url)("/categories/{$key}/products?tab=order"))->assertOk()->assertSeeInOrder(['Tote Bag', 'Sports Smart Watch', 'Linen Summer Dress']);

    $this->deleteJson(($this->url)("/categories/{$key}/products/{$this->watchRow->id}"))->assertOk();
    expect(($this->members)($electronics))->toBe([$this->own->id, $this->dressRow->id]);

    $this->get(($this->url)('/categories/central-999999/products'))->assertNotFound();
    $this->get(($this->url)('/categories/bogus-1/products'))->assertNotFound();

    // The central catalog is untouched.
    expect($this->watch->categories()->pluck('categories.id')->all())->toBe([$this->electronics->id]);
});

it('deletes a store category and takes it off its products', function (): void {
    ($this->as)();
    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::factory()->create());
    $ref = StoreCategoryRef::store($gifts->id);

    $this->postJson(($this->url)("/categories/{$ref->routeKey()}/products"), ['product_ids' => [$this->own->id]])->assertOk();
    expect(($this->members)($ref))->toBe([$this->own->id]);

    $this->patchJson(($this->url)("/categories/{$ref->routeKey()}/featured"), ['is_featured' => 1])->assertOk();
    $this->patchJson(($this->url)("/categories/central-{$this->electronics->id}/featured"), ['is_featured' => 1])->assertNotFound();

    $this->deleteJson(($this->url)("/categories/{$gifts->id}"))->assertOk();
    $this->tenant->run(fn () => expect(StoreCategory::query()->count())->toBe(0)->and(StoreProduct::query()->find($this->own->id)->store_category_ids)->toBe([]));
    $this->deleteJson(($this->url)("/categories/{$gifts->id}"))->assertNotFound();
});

it('sorts platform and store categories together (VEN-017c)', function (): void {
    ($this->as)();
    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::factory()->create(['name' => ['en' => 'Gifts', 'ar' => 'هدايا']]));
    $refs = ["store:{$gifts->id}", "central:{$this->fashion->id}", "central:{$this->electronics->id}"];

    $this->get(($this->url)('/categories/sort'))->assertOk()->assertSee(__('catalog::taxonomy.sort.categories_title'))->assertSee('Gifts');
    $this->postJson(($this->url)('/categories/sort'), ['ids' => array_slice($refs, 0, 2)])->assertUnprocessable();
    $this->postJson(($this->url)('/categories/sort'), ['ids' => ['nope']])->assertJsonValidationErrors('ids.0');
    $this->postJson(($this->url)('/categories/sort'), ['ids' => $refs])->assertOk();

    $order = $this->tenant->run(fn (): array => array_map(static fn ($row): string => $row->ref->key(), app(StoreTaxonomyReader::class)->categories()));
    expect($order)->toBe($refs);
    $this->get(($this->url)('/categories/sort'))->assertSeeInOrder(['Gifts', 'Fashion', 'Electronics']);
    $this->get(($this->url)('/products/sort'))->assertOk()->assertSee(__('catalog::taxonomy.sort.tabs.categories'));
});

it('keeps platform badge products read-only and lets the store add, order and remove its own', function (): void {
    ($this->as)();
    $best = ProductBadge::factory()->type(BadgeType::BestSelling)->create();
    ProductBadgeAssignment::query()->create(['product_badge_id' => $best->id, 'product_id' => $this->watch->id, 'country_id' => 0, 'sort_order' => 1]);
    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::factory()->create());

    $this->get(($this->url)('/badges'))->assertOk()->assertSee((string) $best->name)->assertSee('Sports Smart Watch')->assertSee(__('catalog::taxonomy.badges.admin'));

    $this->postJson(($this->url)("/badges/{$best->id}/products"), ['product_ids' => [$this->own->id, $this->dressRow->id]])->assertOk();
    $members = $this->tenant->run(fn (): array => app(StoreTaxonomyReader::class)->badgeMembers($best));
    expect($members)->toBe(['admin' => [$this->watchRow->id], 'store' => [$this->own->id, $this->dressRow->id]]);

    $this->postJson(($this->url)("/badges/{$best->id}/sort"), ['ids' => [$this->dressRow->id, $this->own->id]])->assertOk();
    expect($this->tenant->run(fn (): array => app(StoreTaxonomyReader::class)->badgeMembers($best)['store']))->toBe([$this->dressRow->id, $this->own->id]);
    $this->postJson(($this->url)("/badges/{$best->id}/sort"), ['ids' => [$this->dressRow->id]])->assertUnprocessable();

    $this->postJson(($this->url)("/badges/{$best->id}/add-category"), ['category' => "store:{$gifts->id}"])->assertUnprocessable()->assertJsonPath('message', __('catalog::taxonomy.badges.category_empty'));
    $this->postJson(($this->url)("/badges/{$best->id}/add-category"), ['category' => 'bad'])->assertJsonValidationErrors('category');

    $this->deleteJson(($this->url)("/badges/{$best->id}/products/{$this->own->id}"))->assertOk();
    $this->deleteJson(($this->url)("/badges/{$best->id}/products/{$this->own->id}"))->assertUnprocessable();
    expect($this->tenant->run(fn (): array => app(StoreTaxonomyReader::class)->badgeMembers($best)))->toBe(['admin' => [$this->watchRow->id], 'store' => [$this->dressRow->id]]);

    // Adding a whole category: the watch (inherited) becomes a store pick.
    $this->postJson(($this->url)("/badges/{$best->id}/add-category"), ['category' => "central:{$this->electronics->id}"])->assertOk();
    expect($this->tenant->run(fn (): array => app(StoreTaxonomyReader::class)->badgeMembers($best)))->toBe(['admin' => [], 'store' => [$this->dressRow->id, $this->watchRow->id]]);

    $this->get(($this->url)('/badges/999999'))->assertNotFound();
    expect(ProductBadgeAssignment::query()->count())->toBe(1);
});

it('isolates one store\'s taxonomy from another store', function (): void {
    [$other, $otherOwner] = catalogStore('taxo-two');
    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::factory()->create(['name' => ['en' => 'Secret Gifts', 'ar' => 'هدايا']]));

    $this->actingAsVendor($otherOwner, $other);
    $this->get($this->tenantUrl($other, 'admin/catalog/categories'))->assertOk()->assertDontSee('Secret Gifts');
    $this->get($this->tenantUrl($other, "admin/catalog/categories/{$gifts->id}/edit"))->assertNotFound();
    $this->get($this->tenantUrl($other, "admin/catalog/categories/store-{$gifts->id}/products"))->assertNotFound();
    $this->postJson($this->tenantUrl($other, 'admin/catalog/categories/central-'.$this->electronics->id.'/products'), ['product_ids' => [$this->own->id]])->assertUnprocessable();
});

it('places a product in store categories from the product pages (VEN-012d / 016c)', function (): void {
    ($this->as)();
    $gifts = $this->tenant->run(fn (): StoreCategory => StoreCategory::factory()->create(['name' => ['en' => 'Gifts', 'ar' => 'هدايا']]));

    $this->get(($this->url)("/products/{$this->watchRow->id}/edit?tab=categories"))->assertOk()->assertSee('name="store_category_ids[]"', false)->assertSee('Gifts');

    $this->putJson(($this->url)("/products/{$this->watchRow->id}"), ['placement' => 1, 'store_category_ids' => [999999], 'is_active' => 1])->assertJsonValidationErrors('store_category_ids.0');
    $this->putJson(($this->url)("/products/{$this->watchRow->id}"), ['placement' => 1, 'category_ids' => [$this->electronics->id], 'store_category_ids' => [$gifts->id], 'is_active' => 1])->assertOk();

    expect(($this->members)(StoreCategoryRef::store($gifts->id)))->toBe([$this->watchRow->id]);
});
