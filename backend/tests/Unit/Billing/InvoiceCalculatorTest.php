<?php

use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Support\InvoiceCalculator;

/*
| Invoice totals: subtotal = Σ unit × qty, tax = subtotal × rate (rounded to the cent),
| total = subtotal + tax — all in USD minor units.
*/

it('sums lines and applies the tax rate in basis points', function (int $rateBp, int $tax, int $total): void {
    $result = InvoiceCalculator::calculate([
        new InvoiceLineData('Plan', 4261),
        new InvoiceLineData('Seats', 333, 3),
    ], $rateBp);

    expect($result['subtotal']->minor)->toBe(5260)
        ->and($result['tax']->minor)->toBe($tax)
        ->and($result['total']->minor)->toBe($total)
        ->and($result['lines'][1]['total']->minor)->toBe(999);
})->with([
    'no tax' => [0, 0, 5260],
    '15 % VAT' => [1500, 789, 6049],
    '5 % VAT' => [500, 263, 5523],
    '14 % VAT' => [1400, 736, 5996],
]);

it('returns zero totals for an empty invoice', function (): void {
    $result = InvoiceCalculator::calculate([], 1500);

    expect($result['total']->isZero())->toBeTrue()
        ->and($result['total']->currency)->toBe('USD');
});
