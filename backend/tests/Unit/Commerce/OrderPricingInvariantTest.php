<?php

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Pricing\AppliedCoupon;
use App\Modules\Commerce\Pricing\OrderComposer;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Modules\Shipping\DTOs\ShippingQuote;
use App\Shared\Money\Money;

/*
| Property-style checks over seeded random carts (ORDER_FORMULAS.md §2 invariant):
| platform_profit + vendor_profit = order_grand_total for every order, the coupon never
| exceeds what it may discount, and the totals add up.
*/

/**
 * @return array{0: list<OrderLineInput>, 1: ShippingQuote, 2: AppliedCoupon|null}
 */
function randomCart(int $seed): array
{
    mt_srand($seed);
    $sources = [null, DiscountSource::Central, DiscountSource::Vendor];
    $lines = [];

    for ($i = 0, $count = mt_rand(1, 6); $i < $count; $i++) {
        $flash = mt_rand(0, 2) === 0 ? mt_rand(1, 9000) / 100 : 0.0;
        $source = $flash > 0 ? $sources[mt_rand(1, 2)] : null;

        if (mt_rand(0, 3) === 0) {
            $lines[] = OrderLineInput::own("o{$i}", Money::ofMinor(mt_rand(1, 500000)), mt_rand(1, 9), $flash, $source);

            continue;
        }

        $rule = mt_rand(0, 1) === 0 ? ProfitRule::percentage(mt_rand(0, 25000) / 100) : ProfitRule::fixed(mt_rand(0, 100000) / 100);
        $unit = UnitPricing::unitPrice(Money::ofMinor(mt_rand(1, 300000)), $rule, Money::ofMinor(mt_rand(0, 30000)));
        $lines[] = OrderLineInput::catalog("c{$i}", $unit, mt_rand(1, 9), mt_rand(0, 4000), $flash, $source);
    }

    $quote = new ShippingQuote(true, Money::ofMinor(mt_rand(0, 5000)), 0);
    $owner = mt_rand(0, 1) === 0 ? DiscountSource::Central : DiscountSource::Vendor;
    $coupon = match (mt_rand(0, 4)) {
        0 => null,
        1 => AppliedCoupon::percentage(mt_rand(1, 10000) / 100, $owner),
        2 => AppliedCoupon::percentage(mt_rand(1, 10000) / 100, $owner, Money::ofMinor(mt_rand(1, 10000))),
        3 => AppliedCoupon::fixed(Money::ofMinor(mt_rand(1, 2000000)), $owner),
        default => AppliedCoupon::freeShipping($owner),
    };

    return [$lines, $quote, $coupon];
}

it('keeps platform_profit + vendor_profit = grand_total on 500 random carts', function (): void {
    for ($seed = 1; $seed <= 500; $seed++) {
        [$lines, $quote, $coupon] = randomCart($seed);
        $composed = OrderComposer::composeWithQuote($lines, $quote, $coupon);

        foreach ($composed->orders() as $order) {
            expect($order->platformProfit->add($order->vendorProfit)->minor)->toBe($order->grandTotal->minor, "seed {$seed}")
                ->and($order->itemsGrandTotal->add($order->shippingCharge)->subtract($order->couponDiscount)->minor)->toBe($order->grandTotal->minor)
                ->and($order->couponDiscount->minor)->toBeGreaterThanOrEqual(0);

            foreach ($order->lines as $line) {
                expect($line->price->minor)->toBe($line->cost->minor + $line->profitAmount->minor)
                    ->and($line->grandTotal->minor)->toBe($line->subTotal->minor + $line->fixedShipping->minor - $line->discount->minor + $line->tax->minor)
                    ->and($line->discount->minor)->toBeLessThanOrEqual($line->subTotal->minor + $line->fixedShipping->minor);
            }
        }

        $coupon = $composed->couponDiscount()->minor;
        $limit = $composed->coupon?->type === CouponType::FreeShipping ? $quote->amount->minor : $composed->itemsGrandTotal()->minor;

        expect($coupon)->toBeLessThanOrEqual($limit, "seed {$seed}")
            ->and($composed->platformProfit()->add($composed->vendorProfit())->minor)->toBe($composed->grandTotal()->minor)
            ->and($composed->companion?->shippingCharge->minor ?? 0)->toBe(0);
    }
});

it('never charges shipping for own-only carts and keeps the coupon share whole across a mixed split', function (): void {
    for ($seed = 501; $seed <= 700; $seed++) {
        [$lines, $quote, $coupon] = randomCart($seed);
        $composed = OrderComposer::composeWithQuote($lines, $quote, $coupon);
        $whole = $coupon?->discountFor($composed->itemsGrandTotal(), $composed->primary?->shippingCharge ?? Money::zero());

        $ownOnly = array_filter($lines, static fn (OrderLineInput $line): bool => ! $line->own) === [];

        expect($composed->couponDiscount()->minor)->toBe($whole?->minor ?? 0, "seed {$seed}")
            ->and($ownOnly ? $composed->shippingCharge()->minor : 0)->toBe(0);
    }
});
