<?php

use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Support\CommissionCalculator;

/*
| Affiliate commission / promo discount math on USD cents: percentage in basis points
| rounded half up to the cent, fixed = min(value, sale), nothing on zero.
*/

it('computes golden commission values', function (RateType $type, int $value, int $sale, int $expected): void {
    expect(CommissionCalculator::amount($type, $value, $sale))->toBe($expected);
})->with([
    '30 % of $49.00' => [RateType::Percentage, 3000, 4900, 1470],
    '30 % of $792.00 (yearly)' => [RateType::Percentage, 3000, 79200, 23760],
    '12.5 % of $19.99 rounds half up' => [RateType::Percentage, 1250, 1999, 250],
    '15 % of $0.03 rounds down' => [RateType::Percentage, 1500, 3, 0],
    '33.33 % of $100.01' => [RateType::Percentage, 3333, 10001, 3333],
    '100 % of $49.00' => [RateType::Percentage, 10000, 4900, 4900],
    'over 100 % is capped' => [RateType::Percentage, 15000, 4900, 4900],
    'fixed $25.00 of $49.00' => [RateType::Fixed, 2500, 4900, 2500],
    'fixed never above the sale' => [RateType::Fixed, 9900, 4900, 4900],
    'zero sale' => [RateType::Percentage, 3000, 0, 0],
    'zero rate' => [RateType::Fixed, 0, 4900, 0],
    'negative sale' => [RateType::Percentage, 3000, -4900, 0],
]);
