<?php

use App\Modules\Inventory\Enums\StockAlertEvent;

/*
| Stock alert crossing rules (decision #124): low stock only when the threshold is
| crossed downwards, out of stock when it reaches zero, restock when it leaves zero.
*/

it('decides the alert of a stock move', function (int $previous, int $current, int $threshold, ?StockAlertEvent $expected): void {
    expect(StockAlertEvent::forChange($previous, $current, $threshold))->toBe($expected);
})->with([
    'crosses the threshold' => [10, 5, 5, StockAlertEvent::LowStock],
    'stays above' => [10, 6, 5, null],
    'already below' => [4, 2, 5, null],
    'reaches zero' => [3, 0, 5, StockAlertEvent::OutOfStock],
    'zero stays zero' => [0, 0, 5, null],
    'threshold off' => [10, 1, 0, null],
    'goes up' => [2, 9, 5, null],
]);

it('detects a restock from zero', function (): void {
    expect(StockAlertEvent::isRestock(0, 3))->toBeTrue()
        ->and(StockAlertEvent::isRestock(1, 3))->toBeFalse()
        ->and(StockAlertEvent::isRestock(0, 0))->toBeFalse();
});
