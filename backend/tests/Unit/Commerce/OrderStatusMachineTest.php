<?php

use App\Modules\Commerce\Enums\OrderStatus;

/*
| Order status state machine (pure enum rules).
*/

it('follows the happy path and its side exits', function (): void {
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Processing))->toBeTrue()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Processing))->toBeTrue()
        ->and(OrderStatus::Processing->canTransitionTo(OrderStatus::Shipped))->toBeTrue()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered))->toBeTrue()
        ->and(OrderStatus::Delivered->canTransitionTo(OrderStatus::Completed))->toBeTrue()
        ->and(OrderStatus::Delivered->canTransitionTo(OrderStatus::Returned))->toBeTrue()
        ->and(OrderStatus::Completed->canTransitionTo(OrderStatus::Refunded))->toBeTrue()
        ->and(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Refunded))->toBeTrue();
});

it('refuses skipping steps and leaving final states', function (): void {
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Shipped))->toBeFalse()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled))->toBeFalse()
        ->and(OrderStatus::Refunded->isFinal())->toBeTrue()
        ->and(OrderStatus::Returned->isFinal())->toBeTrue()
        ->and(OrderStatus::Shipped->isCancellableByCustomer())->toBeFalse()
        ->and(OrderStatus::Processing->isCancellableByCustomer())->toBeTrue();
});
