<?php

use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Enums\ReturnType;

/*
| The one return state machine (ReturnStatus) and the actions offered from each status.
*/

it('follows the return path to closed', function (): void {
    $path = [ReturnStatus::Pending, ReturnStatus::Approved, ReturnStatus::ItemReceived, ReturnStatus::Inspected, ReturnStatus::Refunded, ReturnStatus::Closed];

    foreach (array_slice($path, 0, -1) as $index => $status) {
        expect($status->canTransitionTo($path[$index + 1]))->toBeTrue();
    }

    expect(ReturnStatus::Closed->transitions())->toBe([])
        ->and(ReturnStatus::Inspected->canTransitionTo(ReturnStatus::ExchangeShipped))->toBeTrue()
        ->and(ReturnStatus::ExchangeShipped->canTransitionTo(ReturnStatus::Exchanged))->toBeTrue();
});

it('refuses skipping steps', function (): void {
    expect(ReturnStatus::Pending->canTransitionTo(ReturnStatus::Refunded))->toBeFalse()
        ->and(ReturnStatus::Approved->canTransitionTo(ReturnStatus::Inspected))->toBeFalse()
        ->and(ReturnStatus::ItemReceived->canTransitionTo(ReturnStatus::Rejected))->toBeFalse()
        ->and(ReturnStatus::Refunded->canTransitionTo(ReturnStatus::Pending))->toBeFalse()
        ->and(ReturnStatus::Rejected->holdsQuantity())->toBeFalse()
        ->and(ReturnStatus::Approved->holdsQuantity())->toBeTrue();
});

it('offers only the actions the state machine allows', function (): void {
    expect(ReturnAction::availableFor(ReturnStatus::Pending, ReturnType::Return))->toBe([ReturnAction::Approve, ReturnAction::Reject, ReturnAction::RequestInfo])
        ->and(ReturnAction::availableFor(ReturnStatus::AwaitingInfo, ReturnType::Return))->toContain(ReturnAction::Resume)
        ->and(ReturnAction::availableFor(ReturnStatus::Inspected, ReturnType::Return))->toBe([ReturnAction::Reject, ReturnAction::Refund])
        ->and(ReturnAction::availableFor(ReturnStatus::Inspected, ReturnType::Exchange))->toBe([ReturnAction::Reject, ReturnAction::ShipExchange])
        ->and(ReturnAction::availableFor(ReturnStatus::Closed, ReturnType::Return))->toBe([]);
});

it('reserves the amount of open and completed refunds', function (): void {
    expect(RefundStatus::reservingValues())->toBe(['pending', 'processing', 'completed', 'failed'])
        ->and(RefundStatus::Rejected->isOpen())->toBeFalse()
        ->and(RefundStatus::Failed->isOpen())->toBeTrue();
});
