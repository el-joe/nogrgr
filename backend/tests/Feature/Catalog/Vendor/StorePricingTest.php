<?php

use App\Modules\Catalog\DTOs\Vendor\MarkupData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Modules\Commerce\Enums\ProfitType;
use App\Modules\Shipping\Models\FixedShippingCost;
use App\Shared\Money\Money;

require_once __DIR__.'/VendorCatalogHelpers.php';

/*
| Store prices of imported products (VEN-010a / 012a) — golden values, all maths through
| Commerce\Pricing (ORDER_FORMULAS §1): price = cost + profit_amount (+ fixed shipping of
| the country, charged once per line). Rule precedence: variant[country] → product[country]
| → variant[default] → product[default] → store default %.
*/

beforeEach(function (): void {
    [$this->tenant, $this->owner, $this->sa, $this->ae] = catalogStore();
    $this->central = Product::factory()->create([
        'base_price' => Money::of('24.00'),
        'weight_grams' => 300,
        'fixed_shipping_costs' => [(string) $this->sa->id => 120, (string) $this->ae->id => 0],
    ]);
});

function priceTable(object $test, $product): array
{
    return $test->tenant->run(function () use ($product): array {
        $rows = [];

        foreach (app(StoreProductPricingService::class)->table($product->fresh()) as $row) {
            $rows[$row->iso2.($row->variantLabel ? '/'.$row->variantLabel : '')] = [$row->price->toDecimal(), $row->profit->toDecimal(), $row->shipping->toDecimal()];
        }

        return $rows;
    });
}

it('seeds the store default markup (20 %) and adds the fixed shipping of each country', function (): void {
    $product = storeProduct($this->tenant, $this->central->id);

    expect(priceTable($this, $product))->toEqual([
        'SA' => ['30.00', '4.80', '1.20'],
        'AE' => ['28.80', '4.80', '0.00'],
    ]);
});

it('applies a percentage markup to all countries (golden 45 %)', function (): void {
    $product = storeProduct($this->tenant, $this->central->id);
    $this->tenant->run(fn () => app(StoreProductPricingService::class)->applyMarkup($product->fresh(), new MarkupData(ProfitType::Percentage, 45)));

    expect(priceTable($this, $product))->toEqual([
        'SA' => ['36.00', '10.80', '1.20'],
        'AE' => ['34.80', '10.80', '0.00'],
    ]);
});

it('applies a fixed markup to one country only and rounds percentages like the old system', function (): void {
    $this->central->update(['base_price' => Money::of('19.99')]);
    $product = storeProduct($this->tenant, $this->central->id, ['markup' => ['default' => ['profit_type' => 'percentage', 'profit_value' => 33.333]]]);
    $this->tenant->run(fn () => app(StoreProductPricingService::class)->applyMarkup($product->fresh(), new MarkupData(ProfitType::Fixed, 5, $this->sa->id)));

    // SA: 19.99 + 5.00 + 1.20; AE: 19.99 × 33.333 % = 6.663… → 6.66.
    expect(priceTable($this, $product))->toEqual([
        'SA' => ['26.19', '5.00', '1.20'],
        'AE' => ['26.65', '6.66', '0.00'],
    ]);
});

it('prices variants with their own cost and variant markup over the product markup', function (): void {
    $black = ProductVariant::factory()->create(['product_id' => $this->central->id, 'title' => ['en' => 'Black'], 'price' => Money::of('26.00'), 'position' => 0]);
    ProductVariant::factory()->create(['product_id' => $this->central->id, 'title' => ['en' => 'White'], 'price' => null, 'position' => 1]);
    $product = storeProduct($this->tenant, $this->central->id, [
        'markup' => ['default' => ['profit_type' => 'percentage', 'profit_value' => 50]],
        'variant_markup' => [(string) $black->id => [(string) $this->ae->id => ['profit_type' => 'fixed', 'profit_value' => 10]]],
    ]);

    expect(priceTable($this, $product))->toEqual([
        'SA/Black' => ['40.20', '13.00', '1.20'],
        'AE/Black' => ['36.00', '10.00', '0.00'],
        'SA/White' => ['37.20', '12.00', '1.20'],
        'AE/White' => ['36.00', '12.00', '0.00'],
    ]);
});

it('recomputes the fixed shipping from an approved weight override', function (): void {
    FixedShippingCost::factory()->create(['country_id' => $this->sa->id, 'price_per_gram' => '0.004']);
    $product = storeProduct($this->tenant, $this->central->id, ['markup' => ['default' => ['profit_type' => 'percentage', 'profit_value' => 0]]]);
    $this->tenant->run(fn () => ProductOverride::query()->create(['product_id' => $this->central->id, 'weight_grams' => 250]));

    // 250 g × 0.004 = 1.00 (instead of the central 1.20 for 300 g); AE has no rate → 0.
    expect(priceTable($this, $product))->toEqual([
        'SA' => ['25.00', '0.00', '1.00'],
        'AE' => ['24.00', '0.00', '0.00'],
    ]);
});

it('prices own products from the vendor form (cost 0, no fixed shipping)', function (): void {
    $own = $this->tenant->run(fn () => StoreProduct::factory()->create(['price' => Money::of('49.90'), 'sale_price' => Money::of('39.90')]));

    $price = $this->tenant->run(fn () => app(StoreProductPricingService::class)->ownPrice($own)->toDecimal());

    expect($price)->toBe('39.90');
});
