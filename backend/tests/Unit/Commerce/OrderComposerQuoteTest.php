<?php

use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Pricing\AppliedCoupon;
use App\Modules\Commerce\Pricing\OrderComposer;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Modules\Shipping\Repositories\Contracts\ShippingRuleRepositoryContract;
use App\Modules\Shipping\Services\ShippingQuoteService;
use App\Modules\Shipping\Support\BandDefinition;
use App\Modules\Shipping\Support\RuleDefinition;
use App\Modules\Shipping\Support\ZoneDefinition;
use App\Shared\Money\Money;

/*
| OrderComposer::compose() asks Shipping\ShippingQuoteService for the order shipping
| charge (catalog lines only) instead of re-implementing the weight / zone maths.
*/

function composerWithRule(?RuleDefinition $rule): OrderComposer
{
    $rules = Mockery::mock(ShippingRuleRepositoryContract::class);
    $rules->shouldReceive('definitionForCountry')->andReturn($rule);

    return new OrderComposer(new ShippingQuoteService($rules));
}

it('takes the shipping charge from the weight band of the catalog lines', function (): void {
    $rule = new RuleDefinition('KSA', [new ZoneDefinition('All', [], [
        new BandDefinition('Light', 0, 1000, Money::of(5), 1),
        new BandDefinition('Heavy', 1001, null, Money::of(12), 2),
    ], 1)], 1);
    $catalog = OrderLineInput::catalog('c', UnitPricing::unitPrice(Money::of(20), ProfitRule::percentage(10), Money::of(2)), 3, 400);
    $own = OrderLineInput::own('o', Money::of(15), 10);

    $composed = composerWithRule($rule)->compose([$catalog, $own], 1, AppliedCoupon::percentage(10, DiscountSource::Vendor));

    // 3 × 400 g = 1200 g → Heavy band 12.00; own lines weigh nothing
    expect($composed->shipping->totalWeightGrams)->toBe(1200)
        ->and($composed->primary?->shippingCharge->toDecimal())->toBe('12.00')
        ->and($composed->companion?->shippingCharge->isZero())->toBeTrue()
        ->and($composed->canCheckout())->toBeTrue()
        ->and($composed->platformProfit()->add($composed->vendorProfit())->equals($composed->grandTotal()))->toBeTrue();
});

it('lets own-only carts check out without a shipping rule and blocks catalog carts without one', function (): void {
    $own = OrderLineInput::own('o', Money::of(15), 1);
    $catalog = OrderLineInput::catalog('c', UnitPricing::unitPrice(Money::of(20), ProfitRule::percentage(10)), 1, 400);

    expect(composerWithRule(null)->compose([$own], null)->canCheckout())->toBeTrue()
        ->and(composerWithRule(null)->compose([$catalog], 5)->canCheckout())->toBeFalse()
        ->and(composerWithRule(null)->compose([$catalog], null)->shipping->reason?->value)->toBe('country_required');
});
