<?php

use App\Modules\Commerce\Enums\ProfitType;
use App\Modules\Commerce\Pricing\CountryPriceTable;
use App\Modules\Commerce\Pricing\FixedShipping;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Shared\Money\Money;

/*
| Ported from the old tests/Unit/Orders/OrderPricingTest (unit price part) and
| tests/Unit/TenantPricingServiceTest + the ORDER_FORMULAS.md §1 UK worked example.
*/

it('prices a unit as cost plus percentage or fixed profit', function (): void {
    $percentage = UnitPricing::unitPrice(Money::of(1.72), ProfitRule::percentage(25), Money::of(200));

    expect($percentage->profitAmount->toDecimal())->toBe('0.43')
        ->and($percentage->price->toDecimal())->toBe('2.15')
        ->and($percentage->fixedShipping->toDecimal())->toBe('200.00');

    expect(UnitPricing::unitPrice(Money::of(1.72), ProfitRule::fixed(2))->price->toDecimal())->toBe('3.72');
});

it('reproduces the UK worked example with fixed shipping from weight × rate', function (): void {
    // UK: 0.2 USD per gram, the variant weighs 1000 g → fixed shipping 200.00 (once per line).
    $fixed = FixedShipping::forWeight(1000, '0.200000');
    $unit = UnitPricing::unitPrice(Money::of(1.72), ProfitRule::percentage(25), $fixed);

    expect($fixed->toDecimal())->toBe('200.00')
        ->and($unit->cost->toDecimal())->toBe('1.72')
        ->and($unit->profitAmount->toDecimal())->toBe('0.43')
        ->and($unit->price->toDecimal())->toBe('2.15')
        ->and($unit->displayPrice()->toDecimal())->toBe('202.15');

    // DB sample order #4: cost 1.76 → price 2.20.
    expect(UnitPricing::unitPrice(Money::of(1.76), ProfitRule::percentage(25))->price->toDecimal())->toBe('2.20');
});

it('rounds half up with the legacy float tolerance', function (): void {
    // 0.21 × 2.5 % = 0.00525 → 0.01 ; 20.215 → 20.22
    expect(UnitPricing::profitAmount(Money::of(0.21), ProfitRule::percentage(2.5))->toDecimal())->toBe('0.01')
        ->and(Money::of(20.215)->toDecimal())->toBe('20.22');
});

it('computes fixed shipping per weight and never for zero weight', function (): void {
    expect(FixedShipping::forWeight(350, '0.012345')->toDecimal())->toBe('4.32')
        ->and(FixedShipping::forWeight(0, '1')->isZero())->toBeTrue()
        ->and(FixedShipping::costMap(500, [52 => '0.01', 150 => '0.002']))->toBe(['52' => 500, '150' => 100])
        ->and(FixedShipping::costMap(null, [52 => '0.01']))->toBeNull()
        ->and(FixedShipping::costMap(500, []))->toBeNull();
});

it('prices own products from the vendor sell price with no shipping', function (): void {
    $unit = UnitPricing::ownProduct(Money::of(80));

    expect($unit->cost->isZero())->toBeTrue()
        ->and($unit->profitAmount->toDecimal())->toBe('80.00')
        ->and($unit->fixedShipping->isZero())->toBeTrue();
});

describe('country price table (old TenantPricingService)', function (): void {
    $costs = fn (): array => ['52' => Money::of(2.65), '150' => Money::of(14.4), '184' => Money::of(2.4)];

    it('builds every country key with profit rows and total profit', function () use ($costs): void {
        $result = CountryPriceTable::forCatalog(Money::of(0.84), $costs(), null, 25.0, ['52', '150', '184']);

        expect(array_keys($result['prices']))->toEqualCanonicalizing(['default', '52', '150', '184'])
            ->and($result['prices']['default']->toDecimal())->toBe('1.05')
            ->and($result['prices']['52']->toDecimal())->toBe('3.70')
            ->and($result['prices']['150']->toDecimal())->toBe('15.45')
            ->and($result['default']->toDecimal())->toBe('1.05')
            ->and($result['profit']['52'])->toBe(['profit_type' => 'percentage', 'profit_value' => 25.0, 'total_profit' => 0.21]);
    });

    it('keeps the vendor profit config and seeds missing countries', function (): void {
        $existing = ['default' => ['profit_type' => 'fixed', 'profit_value' => 1.0, 'total_profit' => 1.0]];

        $result = CountryPriceTable::forCatalog(Money::of(2.0), ['52' => Money::of(1.0)], $existing, 10.0, ['52']);

        expect($result['profit']['default']['profit_type'])->toBe('fixed')
            ->and($result['prices']['default']->toDecimal())->toBe('3.00')
            ->and($result['profit']['52']['profit_type'])->toBe('percentage')
            ->and($result['prices']['52']->toDecimal())->toBe('3.20');
    });

    it('gives a product without shipping costs every active country row', function (): void {
        $result = CountryPriceTable::forCatalog(Money::of(10.0), [], null, 20.0, ['52', '184']);

        expect(array_keys($result['prices']))->toEqualCanonicalizing(['default', '52', '184'])
            ->and($result['prices']['52']->toDecimal())->toBe('12.00');
    });

    it('pins a typed default price as a fixed profit on the default row only', function (): void {
        $result = CountryPriceTable::forCatalog(Money::of(0.84), ['52' => Money::of(2.65)], null, 25.0, ['52'], Money::of(2.0));

        expect($result['prices']['default']->toDecimal())->toBe('2.00')
            ->and($result['profit']['default'])->toBe(['profit_type' => 'fixed', 'profit_value' => 1.16, 'total_profit' => 1.16])
            ->and($result['prices']['52']->toDecimal())->toBe('3.70')
            ->and($result['profit']['52']['profit_type'])->toBe('percentage');
    });

    it('never produces a negative profit for an override below cost', function (): void {
        $result = CountryPriceTable::forCatalog(Money::of(5.0), [], null, 10.0, [], Money::of(3.0));

        expect($result['prices']['default']->toDecimal())->toBe('5.00')
            ->and($result['profit']['default']['total_profit'])->toBe(0.0);
    });

    it('applies a typed margin percentage to the default row', function (): void {
        $result = CountryPriceTable::forCatalog(Money::of(10.0), [], null, 10.0, [], null, 33.333);

        expect($result['profit']['default']['profit_value'])->toBe(33.33)
            ->and($result['prices']['default']->toDecimal())->toBe('13.33');
    });

    it('resolves the rule of a country, falling back to default', function (): void {
        $profit = ['default' => ['profit_type' => 'percentage', 'profit_value' => 10], '52' => ['profit_type' => 'fixed', 'profit_value' => 3]];

        expect(CountryPriceTable::ruleFor($profit, 52)->type)->toBe(ProfitType::Fixed)
            ->and(CountryPriceTable::ruleFor($profit, 7)->value)->toBe(10.0)
            ->and(CountryPriceTable::ruleFor(null, 7, 25)->value)->toBe(25.0);
    });
});
