<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Services\Promotions\CouponCatalog;
use App\Modules\Commerce\Services\Promotions\FlashSaleCatalog;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\Country;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| VEN-040 / 040a / 041 / 041a / 041b: the vendor Coupons and Flash sales pages — the
| store's own promotions (CRUD in modals) and the platform's (read-only, hide / show),
| permissions, store isolation and "Add to flash sale" from a product.
*/

beforeEach(function (): void {
    $this->sa = Country::factory()->iso('SA')->create();
    $this->ae = Country::factory()->iso('AE')->create();
    $this->eg = Country::factory()->iso('EG')->create();
    $this->tenant = $this->createTenant('promoshop', ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->central = Product::factory()->create(['name' => ['en' => 'Wireless Earbuds Pro', 'ar' => 'سماعات'], 'sku' => 'EAR-1']);
    [$this->catalog, $this->own] = $this->tenant->run(fn (): array => [
        StoreProduct::factory()->central($this->central->id)->create(),
        StoreProduct::factory()->create(['name' => ['en' => 'Tote Bag', 'ar' => 'حقيبة'], 'sku' => 'TOTE-1']),
    ]);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/marketing'.$path);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->data = fn (string $path, string $query = ''): array => $this->getJson(($this->url)($path.'/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
});

function vendorCouponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'megz15',
        'type' => 'percentage',
        'value' => '15%',
        'min_spend' => '$30.00',
        'usage_limit' => '1000',
        'dates_from' => now()->toDateString(),
        'dates_to' => now()->addMonth()->toDateString(),
        'is_active' => '1',
    ], $overrides);
}

function vendorSalePayload(array $products, array $overrides = []): array
{
    return array_merge([
        'title' => ['en' => 'Tote bags week', 'ar' => 'أسبوع الحقائب'],
        'discount_percentage' => '20%',
        'dates_from' => now()->toDateString(),
        'dates_to' => now()->addDays(3)->toDateString(),
        'products' => $products,
        'is_active' => '1',
    ], $overrides);
}

it('sends guests to the login and forbids staff without promotion permissions', function (): void {
    $this->get(($this->url)('/coupons'))->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->get(($this->url)('/flash-sales'))->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $staff = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'coupons.view', 'flash-sales.view']);

        return $staff;
    });
    $this->actingAsVendor($staff, $this->tenant);

    $this->get(($this->url)('/coupons'))->assertOk()->assertSee(__('commerce::vendor_promotions.coupons.subtitle'))->assertDontSee(__('commerce::coupons.add'));
    $this->get(($this->url)('/flash-sales'))->assertOk()->assertDontSee(__('commerce::flash_sales.add'));

    foreach (['/coupons/create', '/flash-sales/create', '/flash-sales/products/search', "/flash-sales/for-product/{$this->own->id}"] as $path) {
        $this->getJson(($this->url)($path))->assertForbidden();
    }

    $this->postJson(($this->url)('/coupons'), vendorCouponPayload())->assertForbidden();
    $this->putJson(($this->url)('/coupons/1'), vendorCouponPayload())->assertForbidden();
    $this->deleteJson(($this->url)('/coupons/1'))->assertForbidden();
    $this->postJson(($this->url)('/coupons/central/1/visibility?hidden=1'))->assertForbidden();
    $this->postJson(($this->url)('/flash-sales'), [])->assertForbidden();
    $this->deleteJson(($this->url)('/flash-sales/1'))->assertForbidden();
    $this->postJson(($this->url)("/flash-sales/for-product/{$this->own->id}"), ['flash_sale_id' => 1])->assertForbidden();

    $none = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view']);

        return $staff;
    });
    $this->actingAsVendor($none, $this->tenant);

    foreach (['/coupons', '/coupons/data', '/flash-sales', '/flash-sales/data'] as $path) {
        $this->getJson(($this->url)($path))->assertForbidden();
    }
});

it('creates, lists, views, edits and deletes a store coupon with its code kept reserved', function (): void {
    ($this->as)();

    $this->get(($this->url)('/coupons'))->assertOk()->assertSee(__('commerce::vendor_promotions.tabs.own_coupons'))->assertSee(__('commerce::coupons.add'));
    $this->get(($this->url)('/coupons/create'), $this->ajax)->assertOk()->assertSee('vendor-coupon-form', false)->assertSee(__('commerce::vendor_promotions.coupons.deducted'));

    $this->postJson(($this->url)('/coupons'), vendorCouponPayload(['country_ids' => [$this->sa->id, $this->ae->id]]))->assertCreated();
    $coupon = $this->tenant->run(fn () => StoreCoupon::query()->with('countryRows')->firstOrFail());

    expect($coupon->code)->toBe('MEGZ15')
        ->and((float) $coupon->percentage)->toBe(15.0)
        ->and($coupon->min_spend_minor?->minor)->toBe(3000)
        ->and($coupon->countryIds())->toEqualCanonicalizing([$this->sa->id, $this->ae->id]);

    $row = ($this->data)('/coupons')['data'][0];
    expect($row['code'])->toContain('MEGZ15')->toContain('SA')->toContain(__('commerce::vendor_promotions.coupons.all_products'))
        ->and($row['discount'])->toBe('15% off')
        ->and(strip_tags($row['min_spend']))->toBe('$30.00')
        ->and(strip_tags($row['usage']))->toBe('0 / 1,000')
        ->and(($this->data)('/coupons', '&search=megz')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('/coupons', '&status=expired')['recordsFiltered'])->toBe(0)
        ->and(($this->data)('/coupons', '&country_id='.$this->ae->id)['recordsFiltered'])->toBe(1);

    $this->get(($this->url)("/coupons/{$coupon->id}"), $this->ajax)->assertOk()->assertSee('vendor-coupon-view', false);
    $this->get(($this->url)("/coupons/{$coupon->id}/edit"))->assertOk()->assertSee('MEGZ15');

    $this->putJson(($this->url)("/coupons/{$coupon->id}"), vendorCouponPayload(['code' => 'MEGZ15', 'type' => 'fixed', 'value' => '5']))->assertOk();
    $this->tenant->run(fn () => expect(StoreCoupon::query()->firstOrFail()->amount_minor?->minor)->toBe(500));

    $this->deleteJson(($this->url)("/coupons/{$coupon->id}"))->assertOk();
    expect(($this->data)('/coupons')['recordsFiltered'])->toBe(0);

    $this->postJson(($this->url)('/coupons'), vendorCouponPayload())->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('validates the coupon form against the store selling countries', function (): void {
    ($this->as)();

    $this->postJson(($this->url)('/coupons'), vendorCouponPayload([
        'code' => 'a b',
        'value' => '150',
        'dates_to' => now()->subDay()->toDateString(),
        'country_ids' => [$this->eg->id],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['code', 'value', 'dates_to', 'country_ids.0']);

    $this->postJson(($this->url)('/coupons'), [])->assertUnprocessable()->assertJsonValidationErrors(['code', 'type', 'dates_from', 'dates_to']);
});

it('keeps the platform coupons read-only and lets the store hide and show them', function (): void {
    $central = Coupon::factory()->create(['code' => 'NOGRGR10']);
    ($this->as)();

    $this->get(($this->url)('/coupons?tab=central'))->assertOk()->assertSee(__('commerce::vendor_promotions.coupons.central_note'));
    $row = ($this->data)('/coupons', '&tab=central')['data'][0];
    expect($row['code'])->toContain('NOGRGR10')
        ->and($row['actions'])->toContain(__('commerce::vendor_promotions.hide'))->not->toContain('coupons/'.$central->id.'/edit')
        ->and(($this->data)('/coupons')['recordsFiltered'])->toBe(0);

    // The vendor routes only ever address store rows.
    $this->get(($this->url)("/coupons/{$central->id}/edit"), $this->ajax)->assertNotFound();
    $this->putJson(($this->url)("/coupons/{$central->id}"), vendorCouponPayload())->assertNotFound();
    $this->deleteJson(($this->url)("/coupons/{$central->id}"))->assertNotFound();
    $this->postJson(($this->url)('/coupons/central/999999/visibility?hidden=1'))->assertNotFound();

    $this->postJson(($this->url)("/coupons/central/{$central->id}/visibility?hidden=1"))->assertOk();
    $this->tenant->run(fn () => expect(app(CouponCatalog::class)->all()->pluck('code')->all())->not->toContain('NOGRGR10'));
    $row = ($this->data)('/coupons', '&tab=central')['data'][0];
    expect($row['code'])->toContain(__('commerce::vendor_promotions.hidden'))
        ->and($row['actions'])->toContain(__('commerce::vendor_promotions.unhide'));

    $this->postJson(($this->url)("/coupons/central/{$central->id}/visibility?hidden=0"))->assertOk();
    $this->tenant->run(fn () => expect(app(CouponCatalog::class)->all()->pluck('code')->all())->toContain('NOGRGR10'));
    expect($central->fresh()?->trashed())->toBeFalse();
});

it('isolates one store coupons and flash sales from another store', function (): void {
    $other = $this->createTenant('othershop');
    [$theirCoupon, $theirSale] = $other->run(fn (): array => [StoreCoupon::factory()->create(['code' => 'THEIRS']), StoreFlashSale::factory()->create()]);
    ($this->as)();

    expect(($this->data)('/coupons')['recordsFiltered'])->toBe(0)
        ->and(($this->data)('/flash-sales')['recordsFiltered'])->toBe(0);
    $this->getJson(($this->url)("/coupons/{$theirCoupon->id}"))->assertNotFound();
    $this->deleteJson(($this->url)("/coupons/{$theirCoupon->id}"))->assertNotFound();
    $this->deleteJson(($this->url)("/flash-sales/{$theirSale->id}"))->assertNotFound();
    $other->run(fn () => expect(StoreCoupon::query()->count())->toBe(1));

    // A session bound to another store cannot open this store's panel.
    $this->actingAsVendor($this->owner, $other)->getJson(($this->url)('/coupons'))->assertNotFound();
});

it('creates, edits and deletes a store flash sale over imported and own products', function (): void {
    Storage::fake('public');
    ($this->as)();

    $search = $this->getJson(($this->url)('/flash-sales/products/search?q=Earbuds'))->assertOk()->json('results');
    expect($search)->toHaveCount(1)->and($search[0]['id'])->toBe('central:'.$this->central->id)->and($search[0]['text'])->toContain('Wireless Earbuds Pro');
    expect($this->getJson(($this->url)('/flash-sales/products/search'))->json('results'))->toHaveCount(2);

    $this->postJson(($this->url)('/flash-sales'), vendorSalePayload(['central:'.$this->central->id, 'own:'.$this->own->id], [
        'country_ids' => [$this->sa->id],
        'banner' => [UploadedFile::fake()->image('tote-week.jpg', 1200, 400)],
    ]))->assertCreated();

    $sale = $this->tenant->run(fn () => StoreFlashSale::query()->with(['productRows', 'countryRows'])->firstOrFail());
    expect($sale->productRows)->toHaveCount(2)->and($sale->banner_path)->not->toBeNull();

    $row = ($this->data)('/flash-sales')['data'][0];
    expect($row['title'])->toContain('Tote bags week')->toContain('.jpg')
        ->and(strip_tags($row['discount']))->toBe('20%')
        ->and(strip_tags($row['products']))->toBe('2')
        ->and(($this->data)('/flash-sales', '&status=scheduled')['recordsFiltered'])->toBe(0);

    $this->get(($this->url)("/flash-sales/{$sale->id}/edit"), $this->ajax)->assertOk()->assertSee('Wireless Earbuds Pro')->assertSee('Tote Bag');

    $this->putJson(($this->url)("/flash-sales/{$sale->id}"), vendorSalePayload(['own:'.$this->own->id], ['discount_percentage' => '30', 'banner_keep' => ['banner']]))->assertOk();
    $this->tenant->run(function () use ($sale): void {
        $discounts = app(FlashSaleCatalog::class)->discountsFor([ProductRef::own($this->own->id), ProductRef::central($this->central->id)], $this->sa->id);
        expect($discounts)->toHaveKey('own:'.$this->own->id)->not->toHaveKey('central:'.$this->central->id)
            ->and($discounts['own:'.$this->own->id]->percentage)->toBe(30.0)
            ->and($sale->fresh()?->banner_path)->not->toBeNull();
    });

    $this->deleteJson(($this->url)("/flash-sales/{$sale->id}"))->assertOk();
    expect(($this->data)('/flash-sales')['recordsFiltered'])->toBe(0);
});

it('validates the flash sale form and refuses products the store does not sell', function (): void {
    ($this->as)();
    $notImported = Product::factory()->create();

    $this->postJson(($this->url)('/flash-sales'), vendorSalePayload(['own:999999']))->assertUnprocessable()->assertJsonValidationErrors('products');
    $this->postJson(($this->url)('/flash-sales'), vendorSalePayload(['central:'.$notImported->id]))->assertUnprocessable()->assertJsonValidationErrors('products');
    $this->postJson(($this->url)('/flash-sales'), vendorSalePayload(['bogus'], ['discount_percentage' => '120', 'country_ids' => [$this->eg->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['products', 'discount_percentage', 'country_ids.0']);
    $this->postJson(($this->url)('/flash-sales'), [])->assertUnprocessable()->assertJsonValidationErrors(['title.en', 'discount_percentage', 'products']);
});

it('keeps the platform flash sales read-only and lets the store hide and show them', function (): void {
    $central = FlashSale::factory()->create(['title' => ['en' => 'White Friday Electronics']]);
    $central->products()->sync([$this->central->id]);
    ($this->as)();

    $this->get(($this->url)('/flash-sales'))->assertOk()->assertSee('White Friday Electronics');
    $row = ($this->data)('/flash-sales', '&tab=central')['data'][0];
    expect($row['title'])->toContain('White Friday Electronics')->and($row['actions'])->not->toContain('/edit');

    $this->putJson(($this->url)("/flash-sales/{$central->id}"), vendorSalePayload(['own:'.$this->own->id]))->assertNotFound();

    $this->postJson(($this->url)("/flash-sales/central/{$central->id}/visibility?hidden=1"))->assertOk();
    $this->tenant->run(fn () => expect(app(FlashSaleCatalog::class)->all()->where('source', DiscountSource::Central))->toBeEmpty());
    expect(($this->data)('/flash-sales', '&tab=central')['data'][0]['title'])->toContain(__('commerce::vendor_promotions.hidden'));

    $this->postJson(($this->url)("/flash-sales/central/{$central->id}/visibility?hidden=0"))->assertOk();
    $this->tenant->run(fn () => expect(app(FlashSaleCatalog::class)->all()->where('source', DiscountSource::Central))->toHaveCount(1));
});

it('adds a product to a running store flash sale from the product (VEN-041b)', function (): void {
    [$live, $ended] = $this->tenant->run(fn (): array => [
        StoreFlashSale::factory()->create(['title' => ['en' => 'Tote bags week'], 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]),
        StoreFlashSale::factory()->create(['title' => ['en' => 'Old sale'], 'starts_at' => now()->subDays(9), 'ends_at' => now()->subDays(2)]),
    ]);
    ($this->as)();

    $this->get(($this->url)("/flash-sales/for-product/{$this->catalog->id}"), $this->ajax)
        ->assertOk()->assertSee('Wireless Earbuds Pro')->assertSee('Tote bags week')->assertDontSee('Old sale');

    $this->postJson(($this->url)("/flash-sales/for-product/{$this->catalog->id}"), ['flash_sale_id' => $ended->id])->assertUnprocessable()->assertJsonValidationErrors('flash_sale_id');
    $this->postJson(($this->url)("/flash-sales/for-product/{$this->catalog->id}"), ['flash_sale_id' => $live->id])->assertOk()->assertJsonFragment(['message' => __('commerce::vendor_promotions.flash_sales.attach.done', ['product' => 'Wireless Earbuds Pro · EAR-1', 'sale' => 'Tote bags week'])]);
    $this->postJson(($this->url)("/flash-sales/for-product/{$this->catalog->id}"), ['flash_sale_id' => $live->id])->assertOk();
    $this->getJson(($this->url)('/flash-sales/for-product/999999'))->assertNotFound();

    $this->tenant->run(fn () => expect(app(FlashSaleCatalog::class)->discountsFor([ProductRef::central($this->central->id)], $this->sa->id))->toHaveKey('central:'.$this->central->id));
});
