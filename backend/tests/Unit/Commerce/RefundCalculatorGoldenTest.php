<?php

use App\Modules\Commerce\Pricing\RefundableOrder;
use App\Modules\Commerce\Pricing\RefundCalculator;
use App\Shared\Money\Money;

/*
| Refund maths golden values (ORDER_FORMULAS.md §3 "Return refund", decision #71):
| order = line A 58.80 (2 units) + line B 15.00 (1 unit) → items_grand_total 73.80,
| coupon 7.38 (10 %), shipping 5.00 → grand_total 71.42.
| return value of qty of a line = (line.grand_total − coupon × line / items) × qty / line.qty.
*/

function goldenOrder(int $refundedCents = 0, int $lines = 2): RefundableOrder
{
    return new RefundableOrder(Money::of('71.42'), Money::of('73.80'), Money::of('7.38'), Money::of('5.00'), Money::ofMinor($refundedCents), $lines);
}

it('refunds everything that is left on a cancellation', function (): void {
    $full = RefundCalculator::forCancellation(goldenOrder());

    expect($full->itemsAmount->minor)->toBe(6642)
        ->and($full->shippingAmount->minor)->toBe(500)
        ->and($full->amount->minor)->toBe(7142)
        ->and($full->itemsAmount->add($full->shippingAmount)->minor)->toBe(7142);

    $partly = RefundCalculator::forCancellation(goldenOrder(2000));

    expect($partly->amount->minor)->toBe(5142)
        ->and($partly->shippingAmount->minor)->toBe(500)
        ->and($partly->itemsAmount->minor)->toBe(4642);
});

it('values a returned unit as its line grand total less its coupon share', function (): void {
    $one = RefundCalculator::forReturn(goldenOrder(), Money::of('58.80'), 2, 1, sellerFault: false);
    $two = RefundCalculator::forReturn(goldenOrder(), Money::of('58.80'), 2, 2, sellerFault: true);

    expect($one->itemsAmount->minor)->toBe(2646)
        ->and($one->shippingAmount->minor)->toBe(0)
        ->and($one->amount->minor)->toBe(2646)
        ->and($two->itemsAmount->minor)->toBe(5292)
        ->and($two->shippingAmount->minor)->toBe(0);
});

it('adds shipping only when the whole order comes back for a seller-fault reason', function (): void {
    $single = new RefundableOrder(Money::of('18.50'), Money::of('15.00'), Money::of('1.50'), Money::of('5.00'), Money::zero(), 1);

    $fault = RefundCalculator::forReturn($single, Money::of('15.00'), 1, 1, sellerFault: true);
    $mind = RefundCalculator::forReturn($single, Money::of('15.00'), 1, 1, sellerFault: false);

    expect($fault->itemsAmount->minor)->toBe(1350)
        ->and($fault->shippingAmount->minor)->toBe(500)
        ->and($fault->amount->minor)->toBe(1850)
        ->and($mind->shippingAmount->minor)->toBe(0)
        ->and($mind->amount->minor)->toBe(1350);
});

it('takes the restocking fee unless the seller is at fault', function (): void {
    $fee = RefundCalculator::forReturn(goldenOrder(), Money::of('58.80'), 2, 1, sellerFault: false, restockingFeeBps: 1000);
    $waived = RefundCalculator::forReturn(goldenOrder(), Money::of('58.80'), 2, 1, sellerFault: true, restockingFeeBps: 1000);

    expect($fee->returnFee->minor)->toBe(265)
        ->and($fee->amount->minor)->toBe(2381)
        ->and($waived->returnFee->minor)->toBe(0)
        ->and($waived->feeWaived)->toBeTrue()
        ->and($waived->amount->minor)->toBe(2646);
});

it('caps a return refund at what is still refundable', function (): void {
    $capped = RefundCalculator::forReturn(goldenOrder(6142), Money::of('58.80'), 2, 2, sellerFault: false);

    expect($capped->remaining->minor)->toBe(1000)
        ->and($capped->amount->minor)->toBe(1000)
        ->and($capped->capped)->toBeTrue();
});

it('rounds like the old system (half-up to cents)', function (): void {
    $order = new RefundableOrder(Money::of('50.55'), Money::of('50.55'), Money::of('5.00'), Money::zero(), Money::zero(), 3);

    expect(RefundCalculator::forReturn($order, Money::of('20.22'), 3, 1, false)->itemsAmount->minor)->toBe(607);
});

it('keeps Σ line values + shipping equal to the order grand total', function (): void {
    $order = goldenOrder();
    $a = RefundCalculator::forReturn($order, Money::of('58.80'), 2, 2, false)->itemsAmount;
    $b = RefundCalculator::forReturn($order, Money::of('15.00'), 1, 1, false)->itemsAmount;

    expect($a->add($b, Money::of('5.00'))->minor)->toBe(7142);
});

it('accepts a manual refund only up to the refundable amount', function (): void {
    expect(RefundCalculator::manual(goldenOrder(), Money::of('40.00'))->amount->minor)->toBe(4000)
        ->and(fn () => RefundCalculator::manual(goldenOrder(4000), Money::of('31.43')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RefundCalculator::manual(goldenOrder(), Money::zero()))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RefundCalculator::forReturn(goldenOrder(), Money::of('58.80'), 2, 3, false))->toThrow(InvalidArgumentException::class);
});
