<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\FixedShippingCost;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Services\ProductFixedShippingSync;
use App\Modules\Shipping\Services\ProductShippingDetailsService;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;

/*
| VEN-010e pre-wire: per-destination fixed shipping, zone fee and delivery window of a product.
*/

it('returns the per-destination shipping details of a product', function (): void {
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    FixedShippingCost::factory()->create(['country_id' => $sa->id, 'price_per_gram' => '0.004']);
    $rule = ShippingRule::factory()->create();
    $rule->countries()->attach($sa->id);
    $zone = ShippingZone::query()->create(['shipping_rule_id' => $rule->id, 'name' => 'All KSA']);
    $zone->bands()->create(['name' => 'Light', 'min_weight_grams' => 0, 'max_weight_grams' => 1000, 'price' => Money::of(5)]);
    $product = Product::factory()->create(['weight_grams' => 500]);
    app(ProductFixedShippingSync::class)->sync([$product->id]);

    $rows = app(ProductShippingDetailsService::class)->forProduct($product->refresh(), null, collect([$sa, $eg]), CarbonImmutable::parse('2026-11-24 09:00'));

    expect($rows[0])->toMatchArray(['iso2' => 'SA', 'weight_grams' => 500, 'available' => true, 'zone' => 'All KSA', 'band' => 'Light', 'min_date' => '2026-11-26', 'max_date' => '2026-11-29'])
        ->and($rows[0]['fixed_shipping']->toDecimal())->toBe('2.00')
        ->and($rows[0]['zone_fee']->toDecimal())->toBe('5.00')
        ->and($rows[1]['available'])->toBeFalse()
        ->and($rows[1]['fixed_shipping']->isZero())->toBeTrue()
        ->and($rows[1]['message'])->toBe(__('shipping::quote.unavailable.country_not_served'));
});
