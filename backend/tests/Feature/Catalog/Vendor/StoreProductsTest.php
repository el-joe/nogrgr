<?php

use App\Modules\Catalog\Enums\StoreProductChange;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\StoreProduct;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| VEN-010 products list (toggle active / featured, remove, export), VEN-010a price list,
| VEN-010e shipping details, VEN-012 product edit (store data only; central fields 422),
| reads through StoreCatalogReader (central ⊕ approved override).
*/

beforeEach(function (): void {
    [$this->tenant, $this->owner, $this->sa, $this->ae] = catalogStore();
    $this->central = Product::factory()->create([
        'name' => ['en' => 'Wireless Earbuds Pro', 'ar' => 'سماعات لاسلكية برو'],
        'base_price' => Money::of('24.00'),
        'fixed_shipping_costs' => [(string) $this->sa->id => 120],
    ]);
    $this->product = storeProduct($this->tenant, $this->central->id);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/catalog/products'.$path);
});

it('redirects guests, forbids staff without the permission and isolates stores', function (): void {
    $this->get(($this->url)())->assertRedirect();

    $staff = catalogStaff($this->tenant, []);
    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)())->assertForbidden();

    [$beta] = catalogStore('beta');
    $this->actingAsVendor($this->owner, $this->tenant)->get($this->tenantUrl($beta, 'admin/catalog/products'))->assertNotFound();
});

it('renders the card grid and serves the store products as cards', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)())
        ->assertOk()
        ->assertSee('vnd-card-grid', false)
        ->assertSee(__('catalog::vendor.tabs.own'));

    $json = $this->actingAsVendor($this->owner, $this->tenant)
        ->getJson(($this->url)('/data?draw=1&start=0&length=12&search[value]=Earbuds'))
        ->assertOk()
        ->json();

    expect($json['recordsFiltered'])->toBe(1)
        ->and($json['data'][0]['card'])->toContain('Wireless Earbuds Pro')
        ->toContain('$28.80'); // AE: 24 + 20 % default markup, no fixed shipping
});

it('toggles active / featured and removes a product from the store', function (): void {
    Event::fake([StoreProductsChanged::class]);
    $id = $this->product->id;

    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$id}/active"), ['is_active' => 0])->assertOk();
    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$id}/featured"), ['is_featured' => 1])->assertOk();
    $this->actingAsVendor($this->owner, $this->tenant)->patchJson(($this->url)("/{$id}/featured"), [])->assertUnprocessable();

    $fresh = $this->tenant->run(fn () => StoreProduct::query()->find($id));
    expect($fresh->is_active)->toBeFalse()->and($fresh->is_featured)->toBeTrue();

    $this->actingAsVendor($this->owner, $this->tenant)->deleteJson(($this->url)("/{$id}"))->assertOk();
    expect($this->tenant->run(fn () => StoreProduct::query()->count()))->toBe(0)
        ->and(Product::query()->whereKey($this->central->id)->exists())->toBeTrue();
    Event::assertDispatched(StoreProductsChanged::class, fn (StoreProductsChanged $event): bool => $event->change === StoreProductChange::Removed && $event->centralProductIds === [$this->central->id]);
});

it('exports the store prices as CSV and needs the export permission', function (): void {
    $response = $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/export'))->assertOk();

    expect($response->streamedContent())->toContain('Wireless Earbuds Pro')->toContain('30.00');

    $staff = catalogStaff($this->tenant, ['catalog.products.view']);
    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)('/export'))->assertForbidden();
});

it('shows the edit page with central data read-only and the approved override merged in', function (): void {
    $this->tenant->run(function (): void {
        $override = new ProductOverride(['product_id' => $this->central->id]);
        $override->setTranslation('name', 'en', 'Megz Earbuds');
        $override->save();
    });

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/{$this->product->id}/edit"))
        ->assertOk()
        ->assertSee('Megz Earbuds')
        ->assertSee(__('catalog::vendor.edit.tabs.snapshot'))
        ->assertSee('Wireless Earbuds Pro')
        ->assertSee('pricing[product]['.$this->sa->id.'][profit_value]', false);
});

it('saves markups and placement, and refuses protected central fields with 422', function (): void {
    $url = ($this->url)("/{$this->product->id}");

    $this->actingAsVendor($this->owner, $this->tenant)->putJson($url, ['name' => ['en' => 'Hacked'], 'stock' => 999, 'weight_grams' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'stock', 'weight_grams']);

    $this->actingAsVendor($this->owner, $this->tenant)->putJson($url, [
        'is_active' => 1,
        'pricing' => ['product' => [(string) $this->sa->id => ['profit_type' => 'fixed', 'profit_value' => 6]]],
        'placement' => 1,
        'category_ids' => [],
        'badge_ids' => [],
    ])->assertOk();

    $fresh = $this->tenant->run(fn () => StoreProduct::query()->find($this->product->id));
    expect($fresh->markup[(string) $this->sa->id])->toBe(['profit_type' => 'fixed', 'profit_value' => 6])
        ->and($fresh->category_ids)->toBe([])
        ->and($this->central->refresh()->getTranslation('name', 'en'))->toBe('Wireless Earbuds Pro');

    $staff = catalogStaff($this->tenant, ['catalog.products.view']);
    $this->actingAsVendor($staff, $this->tenant)->putJson($url, ['is_active' => 1])->assertForbidden();
});

it('previews unsaved pricing rows without writing them', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)("/{$this->product->id}/pricing/preview"), ['pricing' => ['product' => [(string) $this->sa->id => ['profit_type' => 'percentage', 'profit_value' => 45]]]])
        ->assertOk()
        ->assertJsonFragment(['iso2' => 'SA', 'price' => '$36.00', 'profit' => '$10.80']);

    expect($this->tenant->run(fn () => StoreProduct::query()->find($this->product->id)->markup))->toBeNull();
});

it('previews the price list and applies a markup through a queued run with progress', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/{$this->product->id}/price-list"))
        ->assertOk()
        ->assertSee('price-list-form', false);

    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)("/{$this->product->id}/price-list/preview"), ['profit_type' => 'percentage', 'profit_value' => 45, 'country_id' => $this->ae->id])
        ->assertOk()
        ->assertJsonFragment(['iso2' => 'AE', 'price' => '$34.80'])
        ->assertJsonFragment(['iso2' => 'SA', 'price' => '$30.00']);

    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/price-list'), ['profit_type' => 'percentage', 'profit_value' => 'abc', 'product_ids' => [$this->product->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profit_value');

    $run = $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)('/price-list'), ['profit_type' => 'percentage', 'profit_value' => 45, 'product_ids' => [$this->product->id]])
        ->assertOk()
        ->json('data');

    $this->actingAsVendor($this->owner, $this->tenant)->getJson($run['url'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.percent', 100);

    expect($this->tenant->run(fn () => StoreProduct::query()->find($this->product->id)->markup[(string) $this->sa->id]))->toBe(['profit_type' => 'percentage', 'profit_value' => 45]);
});

it('shows the per-destination shipping details', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)("/{$this->product->id}/shipping"))
        ->assertOk()
        ->assertSee('data-shipping-details', false)
        ->assertSee('(SA)');
});
