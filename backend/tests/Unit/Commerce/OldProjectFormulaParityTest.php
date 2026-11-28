<?php

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Pricing\OrderPricing;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Shared\Money\Money;

/*
| Parity with the old project (docs/plan/release-qa/checkout-parity.md): the expected
| cents below are what the old App\Support\OrderPricing returns for the same inputs.
*/

dataset('old order results', [
    'doc worked example' => [
        [[1.72, 'percentage', 25, 1, 200.0, 10.0, 'central']], 1.2, null,
        ['lines' => [18193], 'items' => 18193, 'coupon' => 0, 'total' => 18313, 'platform' => 18270, 'vendor' => 43, 'return' => 18193],
    ],
    'percentage store coupon' => [
        [[0.95, 'percentage', 25, 3, 0.0, 0.0, null]], 5.0, ['percentage', 10, 'vendor'],
        ['lines' => [357], 'items' => 357, 'coupon' => 36, 'total' => 821, 'platform' => 785, 'vendor' => 36, 'return' => 107],
    ],
    'fixed markup, store flash sale, platform coupon' => [
        [[7.35, 'fixed', 2.15, 3, 1.37, 15.0, 'vendor'], [0.99, 'percentage', 33.5, 7, 0.45, 0.0, null]], 12.49, ['fixed', 5, 'central'],
        ['lines' => [2539, 969], 'items' => 3508, 'coupon' => 500, 'total' => 4257, 'platform' => 3829, 'vendor' => 428, 'return' => 726],
    ],
    'half-cent rounding' => [
        [[10.05, 'percentage', 10, 1, 0.0, 50.0, 'central']], 0.0, ['percentage', 12.5, 'vendor'],
        ['lines' => [553], 'items' => 553, 'coupon' => 69, 'total' => 484, 'platform' => 452, 'vendor' => 32, 'return' => 484],
    ],
    'fixed coupon above the items' => [
        [[1.00, 'percentage', 0, 2, 0.0, 0.0, null]], 4.0, ['fixed', 50, 'vendor'],
        ['lines' => [200], 'items' => 200, 'coupon' => 200, 'total' => 400, 'platform' => 600, 'vendor' => -200, 'return' => 0],
    ],
]);

it('matches the old order formulas to the cent', function (array $lines, float $shipping, ?array $coupon, array $expected): void {
    $priced = [];

    foreach ($lines as $index => [$cost, $profitType, $profit, $quantity, $fixed, $flash, $source]) {
        $unit = UnitPricing::unitPrice(Money::of($cost), $profitType === 'fixed' ? ProfitRule::fixed($profit) : ProfitRule::percentage($profit), Money::of($fixed));
        $priced[] = OrderPricing::item(OrderLineInput::catalog('l'.$index, $unit, $quantity, 100, $flash, $source !== null ? DiscountSource::from($source) : null));
    }

    $items = Money::zero()->add(...array_map(static fn ($line) => $line->grandTotal, $priced));
    $discount = match ($coupon[0] ?? null) {
        'percentage' => OrderPricing::couponDiscount(CouponType::Percentage, $items, (float) $coupon[1]),
        'fixed' => OrderPricing::couponDiscount(CouponType::Fixed, $items, 0.0, Money::of($coupon[1])),
        default => null,
    };
    $order = OrderPricing::order($priced, Money::of($shipping), $discount, $coupon !== null ? DiscountSource::from($coupon[2]) : null);

    expect(array_map(static fn ($line) => $line->grandTotal->minor, $priced))->toBe($expected['lines'])
        ->and($order->itemsGrandTotal->minor)->toBe($expected['items'])
        ->and($order->couponDiscount->minor)->toBe($expected['coupon'])
        ->and($order->grandTotal->minor)->toBe($expected['total'])
        ->and($order->platformProfit->minor)->toBe($expected['platform'])
        ->and($order->vendorProfit->minor)->toBe($expected['vendor'])
        ->and(OrderPricing::returnLineValue($priced[0]->grandTotal, $order->itemsGrandTotal, $order->couponDiscount, 1, $priced[0]->quantity)->minor)->toBe($expected['return']);
})->with('old order results');
