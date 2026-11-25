<?php

use App\Shared\Money\CurrencyMismatchException;
use App\Shared\Money\Money;

it('rounds like the legacy OrderPricing::money()', function (float $value, float $expected): void {
    expect(Money::round2($value))->toBe($expected);
})->with([
    'half up' => [1.005, 1.01],
    'float noise' => [0.1 + 0.2, 0.3],
    'negative half' => [-1.005, -1.01],
    'already rounded' => [12.5, 12.5],
    'tiny' => [0.004, 0.0],
    'binary half' => [2.675, 2.68],
]);

it('builds from decimal amounts into integer minor units', function (): void {
    expect(Money::of('12.345', 'usd')->minor)->toBe(1235)
        ->and(Money::of(19.99, 'SAR')->minor)->toBe(1999)
        ->and(Money::of(-0.015, 'SAR')->minor)->toBe(-2)
        ->and(Money::of(10, 'SAR')->currency)->toBe('SAR');
});

it('adds, subtracts and compares in the same currency', function (): void {
    $a = Money::ofMinor(1050, 'SAR');
    $b = Money::ofMinor(250, 'SAR');

    expect($a->add($b)->minor)->toBe(1300)
        ->and($a->subtract($b)->minor)->toBe(800)
        ->and($a->greaterThan($b))->toBeTrue()
        ->and($b->lessThan($a))->toBeTrue()
        ->and($a->equals(Money::ofMinor(1050, 'SAR')))->toBeTrue()
        ->and($a->negate()->isNegative())->toBeTrue()
        ->and(Money::zero('SAR')->isZero())->toBeTrue();
});

it('refuses to mix currencies', function (): void {
    Money::ofMinor(100, 'SAR')->add(Money::ofMinor(100, 'USD'));
})->throws(CurrencyMismatchException::class);

it('computes percentages and multiplications with legacy rounding', function (): void {
    expect(Money::of(99.99, 'SAR')->percentage(15)->minor)->toBe(1500)
        ->and(Money::of(10.10, 'SAR')->multiply(0.333)->minor)->toBe(336)
        ->and(Money::ofMinor(500, 'SAR')->multiply(3)->minor)->toBe(1500)
        ->and(Money::ofMinor(1000, 'SAR')->divide(3)->minor)->toBe(333);
});

it('allocates without losing cents', function (): void {
    $parts = Money::ofMinor(1000, 'SAR')->allocate(3);

    expect(array_map(fn (Money $m) => $m->minor, $parts))->toBe([334, 333, 333])
        ->and(Money::zero('SAR')->add(...$parts)->minor)->toBe(1000);

    $negative = Money::ofMinor(-1000, 'SAR')->allocate(3);
    expect(array_sum(array_map(fn (Money $m) => $m->minor, $negative)))->toBe(-1000);
});

it('formats as decimal strings and json', function (): void {
    expect(Money::ofMinor(-5, 'SAR')->toDecimal())->toBe('-0.05')
        ->and(Money::ofMinor(123456, 'USD')->toDecimal())->toBe('1234.56')
        ->and((string) Money::ofMinor(100, 'USD'))->toBe('USD 1.00')
        ->and(Money::ofMinor(100, 'USD')->jsonSerialize())->toBe(['amount' => 100, 'currency' => 'USD', 'decimal' => '1.00']);
});

it('rejects invalid currency codes', function (): void {
    new Money(1, 'us');
})->throws(InvalidArgumentException::class);

it('defaults to the USD base currency', function (): void {
    expect(Money::BASE_CURRENCY)->toBe('USD')
        ->and(Money::of(10)->currency)->toBe('USD')
        ->and(Money::ofMinor(1000)->currency)->toBe('USD')
        ->and(Money::zero()->currency)->toBe('USD');
});
