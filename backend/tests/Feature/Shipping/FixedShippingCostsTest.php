<?php

use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\ProductPriceListService;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Jobs\RecomputeFixedShippingCosts;
use App\Modules\Shipping\Models\FixedShippingCost;
use App\Modules\Shipping\Services\ProductFixedShippingSync;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/ShippingHelpers.php';

/*
| Fixed shipping costs (ADM-031 / 031a): permissions, list, calculator, unique country,
| and the precomputed per-country map on central products (read by the price list).
*/

it('redirects guests and forbids admins without fixed-cost permissions', function (): void {
    $cost = FixedShippingCost::factory()->create();

    $this->get($this->centralUrl('/admin/shipping/fixed-costs'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(shippingAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/shipping/fixed-costs'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/shipping/fixed-costs/data'))->assertForbidden();

    $this->actingAs(shippingAdmin(['shipping.fixed-costs.view']), 'admin');
    $this->get($this->centralUrl('/admin/shipping/fixed-costs'))->assertOk()->assertSee(__('shipping::fixed_costs.calculator.title'));
    $this->getJson($this->centralUrl('/admin/shipping/fixed-costs/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/shipping/fixed-costs/{$cost->id}"), [])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/shipping/fixed-costs/{$cost->id}"))->assertForbidden();
});

it('lists fixed costs with the 500 g example and filters by country', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    FixedShippingCost::factory()->create(['country_id' => $sa->id, 'price_per_gram' => '0.004']);
    FixedShippingCost::factory()->create(['country_id' => $eg->id, 'price_per_gram' => '0.003']);

    $json = $this->getJson($this->centralUrl('/admin/shipping/fixed-costs/data?draw=1&start=0&length=10&order[0][column]=1&order[0][dir]=desc&columns[1][data]=price_per_gram&columns[1][name]=price_per_gram&columns[1][orderable]=true'))->assertOk()->json();

    expect($json['recordsTotal'])->toBe(2)
        ->and($json['data'][0]['price_per_gram'])->toContain('$0.0040')
        ->and($json['data'][0]['example'])->toContain('2.00')
        ->and($this->getJson($this->centralUrl('/admin/shipping/fixed-costs/data?draw=1&start=0&length=10&search=EG'))->json('recordsFiltered'))->toBe(1);
});

it('creates, edits and deletes a rate with one rate per country and recomputes product maps', function (): void {
    $this->actingAs(shippingAdmin(['shipping.fixed-costs.manage']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $ae = Country::factory()->iso('AE')->create();
    $product = Product::factory()->create(['weight_grams' => 180]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'weight_grams' => 500]);
    $light = ProductVariant::factory()->create(['product_id' => $product->id, 'weight_grams' => null]);

    $this->getJson($this->centralUrl('/admin/shipping/fixed-costs/create'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('price_per_gram', false);

    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), ['country_id' => $sa->id, 'price_per_gram' => '$0.0040'])->assertCreated();
    $cost = FixedShippingCost::query()->sole();

    expect($cost->price_per_gram)->toBe('0.004000')
        ->and($product->refresh()->fixed_shipping_costs)->toBe([(string) $sa->id => 72])
        ->and($variant->refresh()->fixed_shipping_costs)->toBe([(string) $sa->id => 200])
        ->and($light->refresh()->fixed_shipping_costs)->toBeNull()
        ->and($light->fixedShippingFor($sa->id)?->toDecimal())->toBe('0.72');

    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), ['country_id' => $sa->id, 'price_per_gram' => '0.005'])
        ->assertJsonValidationErrors(['country_id' => __('shipping::fixed_costs.country_taken')]);

    $this->getJson($this->centralUrl("/admin/shipping/fixed-costs/{$cost->id}/edit"), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee('0.0040');
    $this->putJson($this->centralUrl("/admin/shipping/fixed-costs/{$cost->id}"), ['country_id' => $sa->id, 'price_per_gram' => '0.005'])->assertOk();
    expect($product->refresh()->fixed_shipping_costs)->toBe([(string) $sa->id => 90]);

    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), ['country_id' => $ae->id, 'price_per_gram' => '0.003'])->assertCreated();
    expect($product->refresh()->fixed_shipping_costs)->toBe([(string) $sa->id => 90, (string) $ae->id => 54]);

    $this->deleteJson($this->centralUrl("/admin/shipping/fixed-costs/{$cost->id}"))->assertOk();
    expect($product->refresh()->fixed_shipping_costs)->toBe([(string) $ae->id => 54]);
});

it('validates the rate', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), ['country_id' => 999999, 'price_per_gram' => '-1'])
        ->assertJsonValidationErrors(['country_id', 'price_per_gram']);
    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), ['country_id' => Country::factory()->create()->id, 'price_per_gram' => '0.0000001'])
        ->assertJsonValidationErrors(['price_per_gram']);
});

it('queues the catalog recompute instead of doing it inline', function (): void {
    Queue::fake();
    $this->actingAs(shippingAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/shipping/fixed-costs'), ['country_id' => Country::factory()->create()->id, 'price_per_gram' => '0.004'])->assertCreated();

    Queue::assertPushed(RecomputeFixedShippingCosts::class, fn (RecomputeFixedShippingCosts $job) => $job->productIds === null);
});

it('recomputes a product map when the product weight changes', function (): void {
    $sa = Country::factory()->iso('SA')->create();
    FixedShippingCost::factory()->create(['country_id' => $sa->id, 'price_per_gram' => '0.004']);
    $product = Product::factory()->create(['weight_grams' => 1000]);

    $product->update(['weight_grams' => 1240]);
    event(new ProductUpdated($product->id));

    expect($product->refresh()->fixed_shipping_costs)->toBe([(string) $sa->id => 496]);
});

it('calculates the display price through Commerce pricing', function (): void {
    $this->actingAs(shippingAdmin(['shipping.fixed-costs.view']), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $eg = Country::factory()->iso('EG')->create();
    FixedShippingCost::factory()->create(['country_id' => $sa->id, 'price_per_gram' => '0.004']);

    $this->getJson($this->centralUrl("/admin/shipping/fixed-costs/calculate?country_id={$sa->id}&weight_grams=1,240&sale_price=59.00"))
        ->assertOk()->assertJsonPath('configured', true)->assertJsonPath('display', '$63.96')
        ->assertJsonPath('text', 'Display price: $59.00 + 1,240 × $0.0040 = $63.96');

    $this->getJson($this->centralUrl("/admin/shipping/fixed-costs/calculate?country_id={$eg->id}&weight_grams=500&sale_price=10"))
        ->assertOk()->assertJsonPath('configured', false)->assertJsonPath('display', '$10.00');

    $this->getJson($this->centralUrl('/admin/shipping/fixed-costs/calculate?weight_grams=x'))->assertJsonValidationErrors(['country_id', 'weight_grams', 'sale_price']);
});

it('shows the Ship/g rate and the correct shopper price in the product price list', function (): void {
    $this->actingAs(shippingAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    FixedShippingCost::factory()->create(['country_id' => $sa->id, 'price_per_gram' => '0.004']);
    $product = Product::factory()->create(['weight_grams' => 180, 'base_price' => 5900, 'sale_price' => null]);
    app(ProductFixedShippingSync::class)->sync([$product->id]);

    $rows = app(ProductPriceListService::class)->rows($product->refresh());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['rate'])->toBe('0.004000')
        ->and($rows[0]['shipping']->toDecimal())->toBe('0.72')
        ->and($rows[0]['shopper']->toDecimal())->toBe('59.72');

    $this->get($this->centralUrl("/admin/catalog/products/{$product->id}/prices"), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('Ship/g')->assertSee('$0.0040')->assertSee('$59.72');
});
