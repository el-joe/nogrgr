<?php

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Pricing\AppliedCoupon;
use App\Modules\Commerce\Pricing\OrderComposer;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Pricing\OrderPricing;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Modules\Shipping\DTOs\ShippingQuote;
use App\Shared\Money\Money;

/*
| Golden examples of /var/www/multi-vendor/docs/ORDER_FORMULAS.md, ported 1:1 on USD
| cents. Money rounding is the old OrderPricing::money() (half-up to cents), so the
| doc's un-rounded 20.215 is stored as 20.22 exactly like the old code did.
*/

function quoteOf(float $amount): ShippingQuote
{
    return new ShippingQuote(true, Money::of($amount), 0);
}

it('prices the worked storefront example (UK, flash sale 10 %, no coupon) — §1', function (): void {
    $unit = UnitPricing::unitPrice(Money::of(1.72), ProfitRule::percentage(25), Money::of(200));

    expect($unit->profitAmount->toDecimal())->toBe('0.43')
        ->and($unit->price->toDecimal())->toBe('2.15');

    $line = OrderPricing::item(OrderLineInput::catalog('v', $unit, 1, 0, 10, DiscountSource::Central));

    expect($line->subTotal->toDecimal())->toBe('2.15')
        ->and($line->fixedShipping->toDecimal())->toBe('200.00')
        ->and($line->discount->toDecimal())->toBe('20.22') // (2.15 + 200) × 10 % = 20.215 → 20.22
        ->and($line->grandTotal->toDecimal())->toBe('181.93');

    $order = OrderPricing::order([$line], Money::of(1.2));

    expect($order->itemsGrandTotal->toDecimal())->toBe('181.93')
        ->and($order->grandTotal->toDecimal())->toBe('183.13')
        ->and($order->couponDiscount->isZero())->toBeTrue()
        ->and($order->couponSource)->toBeNull()
        // central flash sale: platform = 1.72 + 200 − 20.22 + 1.20, vendor = 0.43
        ->and($order->platformProfit->toDecimal())->toBe('182.70')
        ->and($order->vendorProfit->toDecimal())->toBe('0.43')
        ->and($order->platformProfit->add($order->vendorProfit)->equals($order->grandTotal))->toBeTrue();
});

it('reproduces DB sample order #4 (cost 1.76 → price 2.20 → discount 20.22 → item 181.98) — §1', function (): void {
    $unit = UnitPricing::unitPrice(Money::of(1.76), ProfitRule::percentage(25), Money::of(200));
    $line = OrderPricing::item(OrderLineInput::catalog('v', $unit, 1, 0, 10, DiscountSource::Vendor));

    expect($unit->price->toDecimal())->toBe('2.20')
        ->and($line->discount->toDecimal())->toBe('20.22')
        ->and($line->grandTotal->toDecimal())->toBe('181.98')
        ->and($line->discountSource)->toBe(DiscountSource::Vendor);

    $order = OrderPricing::order([$line], Money::of(1.2));

    // vendor flash sale: vendor = 0.44 − 20.22, platform = 1.76 + 200 + 1.20
    expect($order->vendorProfit->toDecimal())->toBe('-19.78')
        ->and($order->platformProfit->toDecimal())->toBe('202.96')
        ->and($order->platformProfit->add($order->vendorProfit)->equals($order->grandTotal))->toBeTrue();
});

it('charges fixed shipping once per line, not per unit — §1', function (): void {
    $unit = UnitPricing::unitPrice(Money::of(10), ProfitRule::fixed(2.5), Money::of(3));
    $line = OrderPricing::item(OrderLineInput::catalog('v', $unit, 3));

    expect($line->subTotal->toDecimal())->toBe('37.50')
        ->and($line->grandTotal->toDecimal())->toBe('40.50')
        ->and($line->displaySubtotal()->toDecimal())->toBe('40.50')
        ->and($line->discountSource)->toBeNull();
});

it('prices own products from the sell price with cost 0, no fixed shipping, free shipping — §1 own products', function (): void {
    $line = OrderLineInput::own('own', Money::of(49.99), 2);
    $priced = OrderPricing::item($line);

    expect($priced->cost->isZero())->toBeTrue()
        ->and($priced->profitAmount->toDecimal())->toBe('49.99')
        ->and($priced->fixedShipping->isZero())->toBeTrue()
        ->and($priced->grandTotal->toDecimal())->toBe('99.98')
        ->and($priced->weightGrams)->toBe(0)
        ->and(OrderComposer::quoteLines([$line])[0]->shippable)->toBeFalse();

    $composed = OrderComposer::composeWithQuote([$line], new ShippingQuote(true, Money::zero(), 0));

    expect($composed->isMixed())->toBeFalse()
        ->and($composed->primary?->shippingCharge->isZero())->toBeTrue()
        ->and($composed->primary?->vendorProfit->toDecimal())->toBe('99.98')
        ->and($composed->primary?->platformProfit->isZero())->toBeTrue();
});

it('values coupons on items_grand_total: percentage, fixed capped at the cart — OrderPricing::couponDiscount', function (): void {
    $items = Money::of(80.25);

    expect(OrderPricing::couponDiscount(CouponType::Percentage, $items, 10)->toDecimal())->toBe('8.03') // 8.025 → 8.03
        ->and(OrderPricing::couponDiscount(CouponType::Fixed, $items, amount: Money::of(15))->toDecimal())->toBe('15.00')
        ->and(OrderPricing::couponDiscount(CouponType::Fixed, $items, amount: Money::of(100))->toDecimal())->toBe('80.25')
        ->and(OrderPricing::couponDiscount(CouponType::Percentage, $items, 50, maxDiscount: Money::of(20))->toDecimal())->toBe('20.00')
        ->and(OrderPricing::couponDiscount(CouponType::FreeShipping, $items, shippingCharge: Money::of(7.5))->toDecimal())->toBe('7.50');
});

it('splits the profit by coupon owner: central coupon off the platform, vendor coupon off the vendor — §2', function (DiscountSource $source, string $platform, string $vendor): void {
    $unit = UnitPricing::unitPrice(Money::of(40), ProfitRule::percentage(25), Money::of(5));
    $line = OrderPricing::item(OrderLineInput::catalog('v', $unit, 2));
    $order = OrderPricing::order([$line], Money::of(10), Money::of(12), $source);

    expect($order->itemsGrandTotal->toDecimal())->toBe('105.00')
        ->and($order->grandTotal->toDecimal())->toBe('103.00')
        ->and($order->couponSource)->toBe($source)
        ->and($order->platformProfit->toDecimal())->toBe($platform)
        ->and($order->vendorProfit->toDecimal())->toBe($vendor);
})->with([
    'central coupon' => [DiscountSource::Central, '83.00', '20.00'],
    'vendor coupon' => [DiscountSource::Vendor, '95.00', '8.00'],
]);

it('charges a coupon with an unknown owner to the vendor (old rule)', function (): void {
    $line = OrderPricing::item(OrderLineInput::catalog('v', UnitPricing::unitPrice(Money::of(10), ProfitRule::fixed(5)), 1));
    $order = OrderPricing::order([$line], Money::zero(), Money::of(3));

    expect($order->couponSource)->toBe(DiscountSource::Vendor)
        ->and($order->vendorProfit->toDecimal())->toBe('2.00');
});

it('splits a mixed cart: shipping on the catalog order, coupon shared by items_grand_total — OrderComposer::create', function (): void {
    $catalog = OrderLineInput::catalog('c', UnitPricing::unitPrice(Money::of(30), ProfitRule::percentage(20), Money::of(4)), 1, 500);
    $own = OrderLineInput::own('o', Money::of(20), 1);

    $composed = OrderComposer::composeWithQuote([$catalog, $own], quoteOf(6), AppliedCoupon::percentage(10, DiscountSource::Central));

    // items: catalog 36 + 4 = 40.00, own 20.00 → coupon 6.00 → catalog money(6 × 40 / 60) = 4.00, own 2.00
    expect($composed->isMixed())->toBeTrue()
        ->and($composed->primary?->itemsGrandTotal->toDecimal())->toBe('40.00')
        ->and($composed->primary?->shippingCharge->toDecimal())->toBe('6.00')
        ->and($composed->primary?->couponDiscount->toDecimal())->toBe('4.00')
        ->and($composed->companion?->shippingCharge->isZero())->toBeTrue()
        ->and($composed->companion?->couponDiscount->toDecimal())->toBe('2.00')
        ->and($composed->grandTotal()->toDecimal())->toBe('60.00')
        ->and($composed->platformProfit()->add($composed->vendorProfit())->equals($composed->grandTotal()))->toBeTrue();
});

it('keeps a free-shipping coupon on the order that pays the shipping', function (): void {
    $catalog = OrderLineInput::catalog('c', UnitPricing::unitPrice(Money::of(30), ProfitRule::percentage(20)), 1, 500);
    $own = OrderLineInput::own('o', Money::of(20), 1);

    $composed = OrderComposer::composeWithQuote([$catalog, $own], quoteOf(6), AppliedCoupon::freeShipping(DiscountSource::Vendor));

    expect($composed->primary?->couponDiscount->toDecimal())->toBe('6.00')
        ->and($composed->companion?->couponDiscount->isZero())->toBeTrue()
        ->and($composed->grandTotal()->toDecimal())->toBe('56.00')
        ->and($composed->vendorProfit()->toDecimal())->toBe('20.00'); // 6 + 20 − 6
});

it('refunds a returned line minus its coupon share — §3 return refund', function (): void {
    // line 60.00 of items 100.00, coupon 10.00 → (60 − 6) × 1 / 2 = 27.00
    expect(OrderPricing::returnLineValue(Money::of(60), Money::of(100), Money::of(10), 1, 2)->toDecimal())->toBe('27.00')
        ->and(OrderPricing::returnLineValue(Money::of(33.33), Money::of(33.33), Money::zero(), 1, 3)->toDecimal())->toBe('11.11')
        ->and(OrderPricing::returnLineValue(Money::of(5), Money::of(5), Money::of(10), 1, 1)->isZero())->toBeTrue();
});

it('blocks checkout when the destination cannot be served but still prices the cart', function (): void {
    $line = OrderLineInput::catalog('c', UnitPricing::unitPrice(Money::of(10), ProfitRule::percentage(10)), 1, 100);
    $composed = OrderComposer::composeWithQuote([$line], new ShippingQuote(false, Money::zero(), 100));

    expect($composed->canCheckout())->toBeFalse()
        ->and($composed->grandTotal()->toDecimal())->toBe('11.00')
        ->and(OrderComposer::composeWithQuote([], quoteOf(0))->isEmpty())->toBeTrue();
});
