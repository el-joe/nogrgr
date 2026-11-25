<?php

use App\Modules\Commerce\Enums\CollectedBy;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Pricing\OrderSettlement;
use App\Modules\Commerce\Pricing\SettlementFigures;
use App\Shared\Money\Money;

/*
| Settlement figures (ORDER_FORMULAS.md §3) on the Store API golden cart: grand 63.80,
| platform 54.20, vendor 9.60.
*/

function settlement(int $refunded = 0, int $paid = 0, CollectedBy $by = CollectedBy::Vendor, bool $void = false): SettlementFigures
{
    return OrderSettlement::figures(Money::of('63.80'), Money::of('54.20'), Money::of('9.60'), Money::ofMinor($refunded), Money::ofMinor($paid), $by, $void);
}

it('owes the platform its share on vendor-collected orders, less paid settlements', function (): void {
    expect(settlement()->owedToPlatform->minor)->toBe(5420)
        ->and(settlement()->owedToVendor->minor)->toBe(0)
        ->and(settlement(paid: 2000)->owedToPlatform->minor)->toBe(3420)
        ->and(settlement(paid: 6000)->owedToPlatform->minor)->toBe(0)
        ->and(settlement()->vendorPercent)->toBe(15)
        ->and(settlement()->platformPercent)->toBe(85);
});

it('owes the store its share on platform-collected orders', function (): void {
    $figures = settlement(by: CollectedBy::Platform);

    expect($figures->owedToVendor->minor)->toBe(960)
        ->and($figures->owedToPlatform->minor)->toBe(0);
});

it('lets a partial refund lower only the store share and voids a full refund', function (): void {
    expect(settlement(refunded: 500)->vendorShare->minor)->toBe(460)
        ->and(settlement(refunded: 500)->platformShare->minor)->toBe(5420)
        ->and(settlement(refunded: 6380)->void)->toBeTrue()
        ->and(settlement(refunded: 6380)->owedToPlatform->minor)->toBe(0)
        ->and(settlement(void: true)->platformShare->minor)->toBe(0);
});

it('splits the amount due into shipping first and product cost', function (): void {
    $split = OrderSettlement::breakdown(Money::of('54.20'), Money::of('5.00'));
    $small = OrderSettlement::breakdown(Money::of('3.00'), Money::of('5.00'));

    expect($split['shipping']->minor)->toBe(500)->and($split['product']->minor)->toBe(4920)
        ->and($small['shipping']->minor)->toBe(300)->and($small['product']->minor)->toBe(0);
});

it('collects online platform payments for the platform, everything else for the store', function (): void {
    expect(CollectedBy::forOrder(PaymentSource::Central, PaymentStatus::Paid))->toBe(CollectedBy::Platform)
        ->and(CollectedBy::forOrder(PaymentSource::Tenant, PaymentStatus::Paid))->toBe(CollectedBy::Vendor)
        ->and(CollectedBy::forOrder(null, PaymentStatus::CashOnDelivery))->toBe(CollectedBy::Vendor);
});

it('walks the shipping steps and maps them to order statuses', function (): void {
    expect(FulfilmentStatus::Pending->canTransitionTo(FulfilmentStatus::InTransit))->toBeTrue()
        ->and(FulfilmentStatus::Delivered->transitions())->toBe([])
        ->and(FulfilmentStatus::Delayed->canTransitionTo(FulfilmentStatus::Delivered))->toBeTrue()
        ->and(FulfilmentStatus::Pending->canTransitionTo(FulfilmentStatus::Delivered))->toBeFalse()
        ->and(FulfilmentStatus::InTransit->orderStatus())->toBe(OrderStatus::Shipped)
        ->and(FulfilmentStatus::Packed->orderStatus())->toBe(OrderStatus::Processing)
        ->and(FulfilmentStatus::valuesForTab('in_transit'))->toBe(['in_transit', 'out_for_delivery']);
});
