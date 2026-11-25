<?php

use App\Modules\Commerce\Pricing\CountryPriceTable;
use App\Modules\Commerce\Pricing\FixedShipping;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Shared\Money\Money;

/*
| Fixed shipping costs (ADM-031): display price = sale price + weight × rate, once per line.
| Golden values from the Figma calculator / price list and ORDER_FORMULAS.md §1.
*/

it('golden: Figma calculator 59.00 + 1,240 g × 0.0040 = 63.96', function (): void {
    $shipping = FixedShipping::forWeight(1240, '0.004000');
    $unit = UnitPricing::unitPrice(Money::of(59), ProfitRule::percentage(0), $shipping);

    expect($shipping->toDecimal())->toBe('4.96')->and($unit->displayPrice()->toDecimal())->toBe('63.96');
});

it('golden: 500 g example column and the price list rows', function (string $rate, string $example): void {
    expect(FixedShipping::forWeight(500, $rate)->toDecimal())->toBe($example);
})->with([['0.004000', '2.00'], ['0.005000', '2.50'], ['0.003000', '1.50'], ['0.006000', '3.00']]);

it('golden: UK 1000 g × 0.2 = 200 fixed shipping, charged once per line not × qty', function (): void {
    $unit = UnitPricing::unitPrice(Money::of(1.72), ProfitRule::percentage(25), FixedShipping::forWeight(1000, '0.2'));
    $qty = 3;
    $lineSubTotal = $unit->price->multiply($qty);

    expect($lineSubTotal->add($unit->fixedShipping)->toDecimal())->toBe('206.45');
});

it('builds the per-country map in minor units and feeds the catalog price table', function (): void {
    $map = FixedShipping::costMap(180, [5 => '0.004000', 7 => '0.005000', 9 => '0.003000']);

    expect($map)->toBe(['5' => 72, '7' => 90, '9' => 54])
        ->and(FixedShipping::costMap(0, [5 => '0.004']))->toBeNull()
        ->and(FixedShipping::costMap(500, []))->toBeNull();

    $shipping = array_map(fn (int $minor) => Money::ofMinor($minor), $map);
    $table = CountryPriceTable::forCatalog(Money::of(59), $shipping, null, 0.0, [5, 7, 9]);

    expect($table['prices']['5']->toDecimal())->toBe('59.72')
        ->and($table['prices']['7']->toDecimal())->toBe('59.90')
        ->and($table['prices']['9']->toDecimal())->toBe('59.54')
        ->and($table['default']->toDecimal())->toBe('59.00');
});

it('rounds the weight × rate product half up to cents', function (): void {
    expect(FixedShipping::forWeight(333, '0.004500')->toDecimal())->toBe('1.50')
        ->and(FixedShipping::forWeight(1, '0.004000')->toDecimal())->toBe('0.00')
        ->and(FixedShipping::forWeight(125, '0.004000')->toDecimal())->toBe('0.50');
});
