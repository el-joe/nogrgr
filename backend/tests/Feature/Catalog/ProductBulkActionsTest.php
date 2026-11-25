<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Products list bulk select (ADM-010, Figma 44:13636, QA-P2-007): activate, deactivate and
| delete through ProductService, permission-guarded and validated.
*/

it('forbids the bulk endpoint without the manage permission', function (): void {
    $product = Product::factory()->create();
    $this->actingAs(catalogAdmin(['catalog.products.view']), 'admin');

    $this->postJson($this->centralUrl('/admin/catalog/products/bulk'), ['action' => 'delete', 'ids' => [$product->id]])->assertForbidden();
    expect($product->refresh()->trashed())->toBeFalse();
});

it('shows the select column and bulk bar to managers, not on the trash tab', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/products'))
        ->assertOk()
        ->assertSee('data-selectable', false)
        ->assertSee('data-bulk-action="activate"', false)
        ->assertSee('data-bulk-action="deactivate"', false)
        ->assertSee('data-bulk-action="delete"', false)
        ->assertSee(__('catalog::products.bulk.delete_title'));

    $this->get($this->centralUrl('/admin/catalog/products?tab=trash'))
        ->assertOk()
        ->assertDontSee('data-bulk-action', false);

    $this->actingAs(catalogAdmin(['catalog.products.view']), 'admin')
        ->get($this->centralUrl('/admin/catalog/products'))
        ->assertOk()
        ->assertDontSee('data-bulk-action', false);
});

it('activates and deactivates the selected products', function (): void {
    Event::fake([ProductUpdated::class]);
    $this->actingAs(catalogAdmin(), 'admin');
    $drafts = Product::factory()->count(2)->draft()->create();
    $published = Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => now()->subWeek()]);
    $untouched = Product::factory()->draft()->create();

    $this->postJson($this->centralUrl('/admin/catalog/products/bulk'), ['action' => 'activate', 'ids' => [...$drafts->pluck('id')->all(), $published->id]])
        ->assertOk()
        ->assertJsonPath('data.count', 2)
        ->assertJsonPath('message', trans_choice('catalog::products.bulk.done.activate', 2, ['count' => 2]));

    $drafts->each(fn (Product $product) => expect($product->refresh()->status)->toBe(ProductStatus::Published)
        ->and($product->published_at)->not->toBeNull());
    expect($published->refresh()->published_at->lt(now()->subDays(6)))->toBeTrue()
        ->and($untouched->refresh()->status)->toBe(ProductStatus::Draft);
    Event::assertDispatchedTimes(ProductUpdated::class, 2);

    $this->postJson($this->centralUrl('/admin/catalog/products/bulk'), ['action' => 'deactivate', 'ids' => [$published->id]])
        ->assertOk()
        ->assertJsonPath('data.count', 1);

    expect($published->refresh()->status)->toBe(ProductStatus::Draft);
});

it('moves the selected products to the trash', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $products = Product::factory()->count(3)->create();

    $this->postJson($this->centralUrl('/admin/catalog/products/bulk'), ['action' => 'delete', 'ids' => $products->take(2)->pluck('id')->all()])
        ->assertOk()
        ->assertJsonPath('data.count', 2);

    expect(Product::query()->count())->toBe(1)
        ->and(Product::withTrashed()->count())->toBe(3);
});

it('validates the action and the selection', function (array $payload, string $field): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $trashed = Product::factory()->create();
    $trashed->delete();

    $payload = array_map(fn ($value) => $value === 'TRASHED' ? [$trashed->id] : $value, $payload);

    $this->postJson($this->centralUrl('/admin/catalog/products/bulk'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown action' => [['action' => 'archive', 'ids' => [1]], 'action'],
    'no selection' => [['action' => 'activate', 'ids' => []], 'ids'],
    'unknown product' => [['action' => 'activate', 'ids' => [999999]], 'ids.0'],
    'trashed product' => [['action' => 'delete', 'ids' => 'TRASHED'], 'ids.0'],
]);
