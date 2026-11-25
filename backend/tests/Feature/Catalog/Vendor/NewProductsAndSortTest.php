<?php

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\StoreProductChange;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductTenantAssignment;
use App\Modules\Catalog\Models\StoreProduct;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| VEN-011 / 011a new central products (offered only, preview, import selected / all,
| idempotent, nothing copied) and VEN-014 product sort.
*/

beforeEach(function (): void {
    [$this->tenant, $this->owner] = catalogStore();
    [$this->other] = catalogStore('beta');
    $this->open = Product::factory()->create(['name' => ['en' => 'Open Lamp']]);
    $this->mine = Product::factory()->create(['name' => ['en' => 'Reserved Mug'], 'assignment_scope' => AssignmentScope::Selected]);
    ProductTenantAssignment::query()->create(['product_id' => $this->mine->id, 'tenant_id' => $this->tenant->id]);
    $this->theirs = Product::factory()->create(['name' => ['en' => 'Their Chair'], 'assignment_scope' => AssignmentScope::Selected]);
    ProductTenantAssignment::query()->create(['product_id' => $this->theirs->id, 'tenant_id' => $this->other->id]);
    $this->draft = Product::factory()->draft()->create(['name' => ['en' => 'Draft Desk']]);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/catalog/products'.$path);
});

it('lists only published products offered to the store and not added yet', function (): void {
    storeProduct($this->tenant, $this->open->id);

    $json = $this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/new/data?draw=1&start=0&length=12'))->assertOk()->json();
    $cards = collect($json['data'])->pluck('card')->implode(' ');

    expect($json['recordsFiltered'])->toBe(1)
        ->and($cards)->toContain('Reserved Mug')->not->toContain('Open Lamp')->not->toContain('Their Chair')->not->toContain('Draft Desk');

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/new'))->assertOk()->assertSee(__('catalog::vendor.new.add_all'));
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/new/{$this->mine->id}"))->assertOk()->assertSee('Reserved Mug');
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/new/{$this->theirs->id}"))->assertNotFound();
});

it('imports selected products idempotently as store_products rows only', function (): void {
    Event::fake([StoreProductsChanged::class]);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), ['ids' => [$this->open->id]])->assertOk()->assertJsonPath('data.imported', 1);
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), ['ids' => [$this->open->id]])->assertOk()->assertJsonPath('data.imported', 0);

    $rows = $this->tenant->run(fn () => StoreProduct::query()->get());
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->central_product_id)->toBe($this->open->id)
        ->and($rows[0]->getRawOriginal('name'))->toBeNull()
        ->and($rows[0]->price)->toBeNull();
    Event::assertDispatchedTimes(StoreProductsChanged::class, 1);
    Event::assertDispatched(StoreProductsChanged::class, fn (StoreProductsChanged $event): bool => $event->change === StoreProductChange::Imported);
});

it('refuses products not offered to the store and validates the payload', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), ['ids' => [$this->theirs->id]])->assertUnprocessable();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), ['ids' => [$this->draft->id]])->assertUnprocessable();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), [])->assertUnprocessable()->assertJsonValidationErrors('ids');

    $staff = catalogStaff($this->tenant, ['catalog.products.view']);
    $this->actingAsVendor($staff, $this->tenant)->postJson(($this->url)('/new/import'), ['ids' => [$this->open->id]])->assertForbidden();

    expect($this->tenant->run(fn () => StoreProduct::query()->count()))->toBe(0);
});

it('imports every new product with "add all" and then has nothing left', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), ['all' => 1])->assertOk()->assertJsonPath('data.imported', 2);
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/new/import'), ['all' => 1])->assertUnprocessable();

    expect($this->tenant->run(fn () => StoreProduct::query()->pluck('central_product_id')->sort()->values()->all()))
        ->toBe(collect([$this->open->id, $this->mine->id])->sort()->values()->all());
});

it('sorts every store product and refuses a stale list', function (): void {
    $first = storeProduct($this->tenant, $this->open->id, ['sort_order' => 1]);
    $second = storeProduct($this->tenant, $this->mine->id, ['sort_order' => 2]);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/sort'))->assertOk()->assertSee('Open Lamp')->assertSee('data-sort-list', false);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/sort'), ['ids' => [$second->id, $first->id]])->assertOk();
    expect($this->tenant->run(fn () => StoreProduct::query()->orderBy('sort_order')->pluck('id')->all()))->toBe([$second->id, $first->id]);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/sort'), ['ids' => [$second->id]])->assertUnprocessable();
});
