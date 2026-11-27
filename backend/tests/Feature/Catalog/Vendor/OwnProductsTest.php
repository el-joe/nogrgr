<?php

use App\Modules\Catalog\Enums\MediaType;
use App\Modules\Catalog\Events\Store\OwnProductSaved;
use App\Modules\Catalog\Jobs\GenerateVideoPoster;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Shared\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| VEN-016 own products: sections + "see all", inventory stock editing, create / edit with
| countries and variants (validation), toggle, delete; own vs central permissions.
*/

beforeEach(function (): void {
    Storage::fake('public');
    [$this->tenant, $this->owner, $this->sa, $this->ae] = catalogStore();
    $this->category = Category::factory()->create();
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/catalog/own-products'.$path);
    $this->payload = fn (array $overrides = []) => array_replace_recursive([
        'name' => ['en' => 'Handmade Mug', 'ar' => 'كوب يدوي'],
        'summary' => ['en' => 'Stoneware mug', 'ar' => 'كوب حجري'],
        'description' => ['en' => 'Made in our studio.'],
        'sku' => 'MUG-001',
        'weight_grams' => 350,
        'country_ids' => [$this->sa->id],
        'category_ids' => [$this->category->id],
        'price' => '25.00',
        'sale_price' => '19.90',
        'stock' => 12,
        'is_active' => 1,
        'manage_stock' => 1,
        'is_taxable' => 1,
    ], $overrides);
});

it('guards own products by their own permission', function (): void {
    $this->get(($this->url)())->assertRedirect();

    $centralOnly = catalogStaff($this->tenant, ['catalog.products.view', 'catalog.products.manage']);
    $this->actingAsVendor($centralOnly, $this->tenant)->get(($this->url)())->assertForbidden();
    $this->actingAsVendor($centralOnly, $this->tenant)->postJson(($this->url)(), ($this->payload)())->assertForbidden();

    $viewer = catalogStaff($this->tenant, ['catalog.own-products.view']);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)())->assertOk();
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)('/create'))->assertForbidden();
});

it('validates and creates an own product with variants and images', function (): void {
    Event::fake([OwnProductSaved::class]);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/create'))->assertOk()->assertSee('own-product-form', false);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)(), ['name' => ['en' => ''], 'price' => 'x', 'country_ids' => [999999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name.en', 'summary.en', 'description.en', 'price', 'category_ids', 'country_ids.0', 'weight_grams']);

    $this->actingAsVendor($this->owner, $this->tenant)->post(($this->url)(), ($this->payload)([
        'variants' => [
            ['title' => ['en' => 'Blue'], 'price' => '26.00', 'stock' => 4],
            ['title' => ['en' => 'Green'], 'price' => '27.00', 'sale_price' => '22.00', 'stock' => 6],
        ],
        'images' => [UploadedFile::fake()->image('mug.jpg')],
    ]), ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['redirect']);

    $product = $this->tenant->run(fn () => StoreProduct::query()->own()->with(['variants', 'media'])->sole());
    expect($product->getTranslation('name', 'ar'))->toBe('كوب يدوي')
        ->and($product->central_product_id)->toBeNull()
        ->and($product->price->minor)->toBe(2500)
        ->and($product->stock)->toBe(10)
        ->and($product->country_ids)->toBe([$this->sa->id])
        ->and($product->category_ids)->toBe([$this->category->id])
        ->and($product->variants)->toHaveCount(2)
        ->and($product->media)->toHaveCount(1)
        ->and($product->slug)->toBe('handmade-mug');
    Event::assertDispatched(OwnProductSaved::class, fn (OwnProductSaved $event): bool => $event->created && $event->productId === $product->id);

    // SKU unique per store.
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)(), ($this->payload)())->assertUnprocessable()->assertJsonValidationErrors('sku');
});

it('edits, toggles, re-stocks and deletes an own product', function (): void {
    Event::fake([OwnProductSaved::class]);
    $product = $this->tenant->run(fn () => StoreProduct::factory()->create(['price' => Money::of('10.00'), 'stock' => 3]));

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/{$product->id}/edit"))->assertOk()->assertSee($product->getTranslation('name', 'en'));

    $this->actingAsVendor($this->owner, $this->tenant)->putJson(($this->url)("/{$product->id}"), ($this->payload)(['name' => ['en' => 'Renamed Mug'], 'sku' => $product->sku, 'all_countries' => 1, 'country_ids' => []]))->assertOk();
    $fresh = $this->tenant->run(fn () => StoreProduct::query()->find($product->id));
    expect($fresh->getTranslation('name', 'en'))->toBe('Renamed Mug')->and($fresh->country_ids)->toBeNull();
    Event::assertDispatched(OwnProductSaved::class, fn (OwnProductSaved $event): bool => ! $event->created && in_array('name', $event->changedFields, true));

    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$product->id}/active"), ['is_active' => 0])->assertOk();
    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$product->id}/stock"), ['stock' => -1])->assertUnprocessable();
    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$product->id}/stock"), ['stock' => 40])->assertOk()->assertJsonPath('data.stock', 40);

    $fresh = $this->tenant->run(fn () => StoreProduct::query()->find($product->id));
    expect($fresh->is_active)->toBeFalse()->and($fresh->stock)->toBe(40);

    $this->actingAsVendor($this->owner, $this->tenant)->deleteJson(($this->url)("/{$product->id}"))->assertOk();
    expect($this->tenant->run(fn () => StoreProduct::query()->count()))->toBe(0);
});

it('renders the sections, the see-all page and the inventory grid; imported products stay out', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)())->assertOk()->assertSee(__('catalog::vendor.own.empty_title'));

    $own = $this->tenant->run(fn () => StoreProduct::factory()->create(['is_featured' => true]));
    storeProduct($this->tenant, Product::factory()->create()->id);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)())->assertOk()->assertSee($own->getTranslation('name', 'en'))->assertSee(__('catalog::enums.own_product_section.featured'));
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/sections/featured'))->assertOk();
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/sections/unknown'))->assertNotFound();
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/inventory'))->assertOk();

    expect($this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/data?draw=1&start=0&length=12&section=featured'))->json('recordsFiltered'))->toBe(1)
        ->and($this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('/data?draw=1&start=0&length=12&view=inventory'))->json('data.0.card'))->toContain('data-stock-form');

    // An imported product id is not an own product.
    $central = $this->tenant->run(fn () => StoreProduct::query()->central()->sole());
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/{$central->id}/edit"))->assertNotFound();
});

it('stores own product gallery videos (never primary) and removes them with the product', function (): void {
    Queue::fake([GenerateVideoPoster::class]);

    $this->actingAsVendor($this->owner, $this->tenant)->post(($this->url)(), ($this->payload)([
        'images' => [UploadedFile::fake()->create('demo.webm', 500, 'video/webm'), UploadedFile::fake()->image('mug.jpg')],
    ]), ['Accept' => 'application/json'])->assertOk();

    [$product, $gallery] = $this->tenant->run(function (): array {
        $product = StoreProduct::query()->own()->sole();

        return [$product, $product->gallery()->get()];
    });

    expect($gallery)->toHaveCount(2)
        ->and($gallery[0]->type)->toBe(MediaType::Video)
        ->and($gallery[0]->is_primary)->toBeFalse()
        ->and($gallery[0]->path)->toEndWith('.webm')
        ->and($gallery[1]->is_primary)->toBeTrue();
    Queue::assertPushed(GenerateVideoPoster::class, 1);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/'.$product->id.'/edit'))->assertOk()->assertSee('data-video-tile', false);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)(), ($this->payload)([
        'sku' => 'MUG-002',
        'images' => [UploadedFile::fake()->create('demo.mkv', 100, 'video/x-matroska')],
    ]))->assertUnprocessable()->assertJsonValidationErrors('images.0');
});
