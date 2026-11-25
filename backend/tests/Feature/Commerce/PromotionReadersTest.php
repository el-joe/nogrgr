<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\DTOs\CouponData;
use App\Modules\Commerce\DTOs\FlashSaleData;
use App\Modules\Commerce\DTOs\Promotions\CouponCheck;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\CouponRejection;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Exceptions\PromotionException;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Services\Promotions\CentralPromotionVisibilityService;
use App\Modules\Commerce\Services\Promotions\CouponCatalog;
use App\Modules\Commerce\Services\Promotions\CouponEligibilityService;
use App\Modules\Commerce\Services\Promotions\FlashSaleCatalog;
use App\Modules\Commerce\Services\Promotions\StoreCouponService;
use App\Modules\Commerce\Services\Promotions\StoreFlashSaleService;
use App\Modules\Localization\Models\Country;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;

/*
| Merge readers (decision #65 b): central (admin) + store promotions read together and
| tagged with `source`, never copied; store scope, vendor-hidden central rows, country
| filtering (trimmed to the store's selling countries) and the coupon rules.
*/

beforeEach(function (): void {
    $this->sa = Country::factory()->iso('SA')->create();
    $this->ae = Country::factory()->iso('AE')->create();
    $this->eg = Country::factory()->iso('EG')->create();
    $this->tenant = $this->createTenant('promo', ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $this->other = $this->createTenant('other');
});

it('merges central and store coupons with source tags, store scope and country trimming', function (): void {
    $all = Coupon::factory()->create(['code' => 'ALLSTORES']);
    $selected = Coupon::factory()->create(['code' => 'ONLYPROMO', 'store_scope' => PromotionScope::SelectedStores]);
    $selected->tenants()->sync([$this->tenant->id]);
    $notMine = Coupon::factory()->create(['code' => 'ONLYOTHER', 'store_scope' => PromotionScope::SelectedStores]);
    $notMine->tenants()->sync([$this->other->id]);
    $egypt = Coupon::factory()->create(['code' => 'EGYPTONLY']);
    $egypt->countries()->sync([$this->eg->id]);
    $gulf = Coupon::factory()->create(['code' => 'GULF']);
    $gulf->countries()->sync([$this->sa->id, $this->eg->id]);
    Coupon::factory()->create(['code' => 'GONE'])->delete();

    $this->initializeTenancy($this->tenant);
    $own = app(StoreCouponService::class)->create(new CouponData(code: 'MYSTORE10', type: CouponType::Percentage, percentage: 10, countryIds: [$this->ae->id]));

    $coupons = app(CouponCatalog::class)->all();

    expect($coupons->map(fn ($coupon) => $coupon->source->value.':'.$coupon->code)->all())
        ->toBe(['vendor:MYSTORE10', 'central:ALLSTORES', 'central:ONLYPROMO', 'central:GULF'])
        ->and($coupons->firstWhere('code', 'GULF')?->countryIds)->toBe([$this->sa->id])
        ->and(app(CouponCatalog::class)->all($this->sa->id)->pluck('code')->all())->toBe(['ALLSTORES', 'ONLYPROMO', 'GULF'])
        ->and(app(CouponCatalog::class)->all($this->ae->id)->pluck('code')->all())->toBe(['MYSTORE10', 'ALLSTORES', 'ONLYPROMO'])
        ->and($own->getConnectionName())->not->toBe($all->getConnectionName())
        ->and(StoreCoupon::query()->count())->toBe(1); // central rows are never copied into the store
});

it('lets a store coupon shadow a central coupon with the same code', function (): void {
    Coupon::factory()->create(['code' => 'SAVE10']);
    $this->initializeTenancy($this->tenant);
    StoreCoupon::factory()->create(['code' => 'save10']);

    $coupon = app(CouponCatalog::class)->findByCode('Save10');

    expect($coupon?->source)->toBe(DiscountSource::Vendor)
        ->and(app(CouponCatalog::class)->all()->count())->toBe(1);
});

it('leaves a vendor-hidden central coupon and flash sale out of the store reads', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'HIDEME']);
    $product = Product::factory()->create();
    $sale = FlashSale::factory()->create();
    $sale->products()->sync([$product->id]);

    $this->initializeTenancy($this->tenant);
    app(CentralPromotionVisibilityService::class)->setHidden(PromotionKind::Coupon, $coupon->id, true);
    app(CentralPromotionVisibilityService::class)->setHidden(PromotionKind::FlashSale, $sale->id, true);

    expect(app(CouponCatalog::class)->findByCode('HIDEME'))->toBeNull()
        ->and(app(CouponCatalog::class)->all()->isEmpty())->toBeTrue()
        ->and(app(CouponCatalog::class)->all(includeHidden: true)->sole()->hidden)->toBeTrue()
        ->and(app(FlashSaleCatalog::class)->discountsFor([ProductRef::central($product->id)], $this->sa->id))->toBe([])
        ->and(app(FlashSaleCatalog::class)->all(includeHidden: true)->sole()->hidden)->toBeTrue();

    app(CentralPromotionVisibilityService::class)->setHidden(PromotionKind::Coupon, $coupon->id, false);
    expect(app(CouponCatalog::class)->findByCode('hideme')?->code)->toBe('HIDEME');
});

it('picks the best live flash sale per product and records who funds it', function (): void {
    $phone = Product::factory()->create();
    $case = Product::factory()->create();
    $central = FlashSale::factory()->create(['discount_percentage' => 20]);
    $central->products()->sync([$phone->id, $case->id]);
    $egyptOnly = FlashSale::factory()->create(['discount_percentage' => 50]);
    $egyptOnly->products()->sync([$phone->id]);
    $egyptOnly->countries()->sync([$this->eg->id]);
    FlashSale::factory()->scheduled()->create(['discount_percentage' => 60])->products()->sync([$phone->id]);

    $this->initializeTenancy($this->tenant);
    $service = app(StoreFlashSaleService::class);
    $service->create(new FlashSaleData(['en' => 'Store week'], 30, CarbonImmutable::now()->subHour(), CarbonImmutable::now()->addDay(), products: [ProductRef::central($case->id), ProductRef::own(7)]));
    $service->create(new FlashSaleData(['en' => 'Tie'], 20, CarbonImmutable::now()->subHour(), CarbonImmutable::now()->addDay(), products: [ProductRef::central($phone->id)]));

    $discounts = app(FlashSaleCatalog::class)->discountsFor([ProductRef::central($phone->id), ProductRef::central($case->id), ProductRef::own(7), ProductRef::own(8)], $this->sa->id);

    expect($discounts['central:'.$phone->id]->percentage)->toBe(20.0)
        ->and($discounts['central:'.$phone->id]->source)->toBe(DiscountSource::Central) // tie → central
        ->and($discounts['central:'.$case->id]->percentage)->toBe(30.0)
        ->and($discounts['central:'.$case->id]->source)->toBe(DiscountSource::Vendor)
        ->and($discounts['own:7']->source)->toBe(DiscountSource::Vendor)
        ->and($discounts)->not->toHaveKey('own:8')
        ->and(app(FlashSaleCatalog::class)->all($this->sa->id)->pluck('source')->map->value->all())->toBe(['vendor', 'vendor', 'central', 'central'])
        // EG is not a selling country of the store: the Egypt-only central sale is never offered
        ->and(app(FlashSaleCatalog::class)->all($this->eg->id, liveOnly: true)->pluck('id')->all())->not->toContain($egyptOnly->id)
        ->and(app(FlashSaleCatalog::class)->all(liveOnly: true)->count())->toBe(3);
});

it('applies the coupon rules: window, country, minimum spend, limits and first order', function (): void {
    $limited = Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1, 'min_spend_minor' => 4000]);
    Coupon::factory()->create(['code' => 'LATER', 'starts_at' => now()->addDay()]);
    Coupon::factory()->create(['code' => 'OLD', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
    Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
    Coupon::factory()->create(['code' => 'FIRST', 'first_order_only' => true]);
    Coupon::factory()->create(['code' => 'TWICE', 'per_customer_limit' => 1]);
    Coupon::factory()->create(['code' => 'SHIPFREE', 'type' => CouponType::FreeShipping, 'percentage' => null]);
    Coupon::factory()->create(['code' => 'CAPPED', 'percentage' => 50, 'max_discount_minor' => 2000]);
    Coupon::factory()->create(['code' => 'UAE'])->countries()->sync([$this->ae->id]);

    $this->initializeTenancy($this->tenant);
    $rules = app(CouponEligibilityService::class);
    $cart = fn (int $items, ?int $customer = null, bool $first = false, ?int $shipping = null): CouponCheck => new CouponCheck(Money::ofMinor($items), $this->sa->id, $customer, $first, $shipping === null ? null : Money::ofMinor($shipping));

    expect($rules->check('nope', $cart(5000))->rejection)->toBe(CouponRejection::NotFound)
        ->and($rules->check('LATER', $cart(5000))->rejection)->toBe(CouponRejection::NotStarted)
        ->and($rules->check('OLD', $cart(5000))->rejection)->toBe(CouponRejection::Expired)
        ->and($rules->check('OFF', $cart(5000))->rejection)->toBe(CouponRejection::Inactive)
        ->and($rules->check('UAE', $cart(5000))->rejection)->toBe(CouponRejection::CountryNotAllowed)
        ->and($rules->check('ONCE', $cart(3999))->rejection)->toBe(CouponRejection::MinimumSpend)
        ->and($rules->check('ONCE', $cart(3999))->message())->toContain('40.00')
        ->and($rules->check('FIRST', $cart(5000))->rejection)->toBe(CouponRejection::SignInRequired)
        ->and($rules->check('FIRST', $cart(5000, 9))->rejection)->toBe(CouponRejection::FirstOrderOnly)
        ->and($rules->check('FIRST', $cart(5000, 9, true))->accepted())->toBeTrue()
        ->and($rules->check('SHIPFREE', $cart(5000, shipping: 0))->rejection)->toBe(CouponRejection::NothingToDiscount)
        ->and($rules->check('SHIPFREE', $cart(5000, shipping: 750))->discount?->minor)->toBe(750)
        ->and($rules->check('CAPPED', $cart(10000))->discount?->minor)->toBe(2000);

    $once = $rules->check('ONCE', $cart(5000, 3));
    expect($once->accepted())->toBeTrue()->and($once->discount?->minor)->toBe(500);
    $rules->recordRedemption($once->coupon, 3, 'ORD-1', $once->discount);
    $rules->recordRedemption($once->coupon, 3, 'ORD-1', $once->discount); // idempotent per order
    expect($rules->usage($once->coupon))->toBe(1)
        ->and($rules->check('ONCE', $cart(5000, 4))->rejection)->toBe(CouponRejection::UsageLimitReached);

    $twice = $rules->check('TWICE', $cart(5000, 5));
    $rules->recordRedemption($twice->coupon, 5, 'ORD-2', $twice->discount);
    expect($rules->check('TWICE', $cart(5000, 5))->rejection)->toBe(CouponRejection::CustomerLimitReached)
        ->and($rules->check('TWICE', $cart(5000, 6))->accepted())->toBeTrue();

    // the central usage limit counts the redemptions of every store
    $this->endTenancy();
    $this->initializeTenancy($this->other);
    expect(app(CouponEligibilityService::class)->check('ONCE', new CouponCheck(Money::ofMinor(5000), null, 1))->rejection)->toBe(CouponRejection::UsageLimitReached)
        ->and($limited->redemptions()->withoutGlobalScopes()->count())->toBe(1);
});

it('refuses to read promotions outside a store', function (): void {
    app(CouponCatalog::class)->all();
})->throws(PromotionException::class);
