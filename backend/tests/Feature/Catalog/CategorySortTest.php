<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CategoryService;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Category sort (ADM-014b) and product order inside a category (ADM-014c): pages,
| permissions, tree integrity (every category once, no cycles, depth cap) and persistence.
*/

it('forbids the sort pages and endpoints without catalog.categories.manage', function (): void {
    $category = Category::factory()->create();
    $this->actingAs(catalogAdmin(['catalog.categories.view']), 'admin');

    $this->getJson($this->centralUrl('/admin/catalog/categories/sort'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/catalog/categories/{$category->id}/products"))->assertForbidden();
    $this->putJson($this->centralUrl("/admin/catalog/categories/{$category->id}/products/order"), ['ids' => [1]])->assertForbidden();
});

it('renders both sort tabs with their states', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/categories/sort'))->assertOk()->assertSee(__('catalog::categories.empty_title'));

    $category = Category::factory()->create(['name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات']]);
    $this->get($this->centralUrl('/admin/catalog/categories/sort'))->assertOk()->assertSee('Electronics')->assertSee(route('admin.catalog.categories.tree'), false);
    $this->get($this->centralUrl("/admin/catalog/categories/{$category->id}/products"))->assertOk()->assertSee(__('catalog::categories.sort_page.no_products_title'));

    Product::factory()->inCategory($category)->create(['name' => ['en' => 'Smart Watch', 'ar' => 'ساعة']]);
    $this->get($this->centralUrl("/admin/catalog/categories/{$category->id}/products"))->assertOk()->assertSee('Smart Watch');
});

it('saves the whole tree with new parents and positions', function (): void {
    $this->actingAs(catalogAdmin(['catalog.categories.manage']), 'admin');
    $a = Category::factory()->create(['sort_order' => 0]);
    $b = Category::factory()->create(['sort_order' => 1]);
    $c = Category::factory()->childOf($a)->create();

    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [
        ['id' => $b->id, 'children' => [['id' => $a->id, 'children' => []]]],
        ['id' => $c->id],
    ]])->assertOk()->assertJson(['success' => true]);

    expect($b->refresh()->only(['parent_id', 'sort_order']))->toBe(['parent_id' => null, 'sort_order' => 0])
        ->and($a->refresh()->only(['parent_id', 'sort_order']))->toBe(['parent_id' => $b->id, 'sort_order' => 0])
        ->and($c->refresh()->only(['parent_id', 'sort_order']))->toBe(['parent_id' => null, 'sort_order' => 1]);
});

it('rejects trees that repeat, miss or invent categories, or nest too deep', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $a = Category::factory()->create();
    $b = Category::factory()->childOf($a)->create();

    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [
        ['id' => $a->id, 'children' => [['id' => $a->id]]],
        ['id' => $b->id],
    ]])->assertUnprocessable()->assertJson(['message' => __('catalog::categories.tree_invalid')]);

    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [['id' => $a->id]]])
        ->assertUnprocessable()->assertJson(['message' => __('catalog::categories.tree_stale')]);

    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [['id' => $a->id], ['id' => $b->id], ['id' => 999999]]])
        ->assertUnprocessable();

    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => 'nope'])->assertJsonValidationErrors('tree');
    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [['name' => 'x']]])->assertJsonValidationErrors('tree');

    $chain = Category::factory()->count(CategoryService::MAX_DEPTH)->create();
    $deep = null;
    foreach ($chain->reverse() as $category) {
        $deep = ['id' => $category->id, 'children' => $deep === null ? [] : [$deep]];
    }
    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [
        ['id' => $a->id, 'children' => [$deep]],
        ['id' => $b->id],
    ]])->assertUnprocessable();

    expect($b->refresh()->parent_id)->toBe($a->id);
});

it('persists the product order inside a category', function (): void {
    $this->actingAs(catalogAdmin(['catalog.categories.manage']), 'admin');
    $category = Category::factory()->create();
    $first = Product::factory()->inCategory($category, 0)->create();
    $second = Product::factory()->inCategory($category, 1)->create();
    $third = Product::factory()->inCategory($category, 2)->create();

    $this->putJson($this->centralUrl("/admin/catalog/categories/{$category->id}/products/order"), ['ids' => [$third->id, $first->id, $second->id]])
        ->assertOk()->assertJson(['success' => true]);

    expect($category->products()->pluck('products.id')->all())->toBe([$third->id, $first->id, $second->id]);
});

it('rejects a product order that does not match the category products', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $category = Category::factory()->create();
    $product = Product::factory()->inCategory($category)->create();
    $stranger = Product::factory()->create();

    $this->putJson($this->centralUrl("/admin/catalog/categories/{$category->id}/products/order"), ['ids' => [$product->id, $stranger->id]])
        ->assertUnprocessable()->assertJson(['message' => __('catalog::categories.products_stale')]);
    $this->putJson($this->centralUrl("/admin/catalog/categories/{$category->id}/products/order"), ['ids' => [$product->id, $product->id]])
        ->assertJsonValidationErrors('ids.0');
});
