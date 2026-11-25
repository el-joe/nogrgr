<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Product sort (ADM-012): page, paged data in storefront order with filters, saving a
| window's order and resetting to newest first.
*/

it('renders the sort page and serves products in storefront order', function (): void {
    $this->actingAs(catalogAdmin(['catalog.products.manage']), 'admin');
    $category = Category::factory()->create();
    $a = Product::factory()->create(['sort_order' => 2]);
    $b = Product::factory()->inCategory($category)->create(['sort_order' => 1]);

    $this->get($this->centralUrl('/admin/catalog/products/sort'))->assertOk()->assertSee(__('catalog::products.sort.title'));

    expect($this->getJson($this->centralUrl('/admin/catalog/products/sort/data'))->json('data.*.id'))->toBe([$b->id, $a->id])
        ->and($this->getJson($this->centralUrl("/admin/catalog/products/sort/data?category_id={$category->id}"))->json('data.*.id'))->toBe([$b->id]);
});

it('saves the order of the loaded window and resets to newest', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    [$a, $b, $c] = Product::factory()->count(3)->create(['sort_order' => 0])->all();

    // All tied at 0 → storefront order is newest first: c, b, a.
    $this->putJson($this->centralUrl('/admin/catalog/products/sort'), ['ids' => [$a->id, $c->id, $b->id]])->assertOk();
    expect($this->getJson($this->centralUrl('/admin/catalog/products/sort/data'))->json('data.*.id'))->toBe([$a->id, $c->id, $b->id]);

    $this->putJson($this->centralUrl('/admin/catalog/products/sort'), ['ids' => [$a->id, 999999]])->assertUnprocessable();

    $this->postJson($this->centralUrl('/admin/catalog/products/sort/reset'))->assertOk();
    expect($this->getJson($this->centralUrl('/admin/catalog/products/sort/data'))->json('data.*.id'))->toBe([$c->id, $b->id, $a->id]);
});
