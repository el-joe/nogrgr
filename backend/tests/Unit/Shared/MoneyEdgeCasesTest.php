<?php

use App\Shared\Money\CurrencyMismatchException;
use App\Shared\Money\Money;

/*
| Edge cases of the shared Money value (QA, Phase 0 gate). The legacy formulas
| (ORDER_FORMULAS.md) depend on these: no lost cents, symmetric negative
| rounding and strict currency checks.
*/

function minorUnits(array $parts): array
{
    return array_map(fn (Money $money): int => $money->minor, $parts);
}

it('allocates remainders to the first parts and always sums back', function (int $amount, int $parts, array $expected): void {
    $allocated = Money::ofMinor($amount)->allocate($parts);

    expect(minorUnits($allocated))->toBe($expected)
        ->and(array_sum(minorUnits($allocated)))->toBe($amount)
        ->and(array_unique(array_map(fn (Money $money): string => $money->currency, $allocated)))->toBe(['USD']);
})->with([
    'exact split' => [900, 3, [300, 300, 300]],
    'one cent over' => [1001, 4, [251, 250, 250, 250]],
    'more parts than cents' => [2, 5, [1, 1, 0, 0, 0]],
    'single part' => [1234, 1, [1234]],
    'zero' => [0, 3, [0, 0, 0]],
    'negative remainder' => [-1001, 4, [-251, -250, -250, -250]],
    'negative, more parts than cents' => [-2, 3, [-1, -1, 0]],
]);

it('refuses to allocate into less than one part', function (int $parts): void {
    Money::ofMinor(100)->allocate($parts);
})->with([0, -1])->throws(InvalidArgumentException::class);

it('refuses division by zero', function (int|float $divisor): void {
    Money::ofMinor(100)->divide($divisor);
})->with([0, 0.0])->throws(InvalidArgumentException::class);

it('rounds negative amounts symmetrically to positive ones', function (string $amount, int $expected): void {
    expect(Money::of($amount)->minor)->toBe($expected)
        ->and(Money::of(ltrim($amount, '-'))->minor)->toBe(abs($expected));
})->with([
    ['-1.005', -101],
    ['-2.675', -268],
    ['-0.004', 0],
    ['-0.005', -1],
    ['-19.999', -2000],
]);

it('keeps negative amounts through arithmetic, abs, min and max', function (): void {
    $refund = Money::ofMinor(-1250);
    $charge = Money::ofMinor(1000);

    expect($refund->add($charge)->minor)->toBe(-250)
        ->and($charge->subtract($refund)->minor)->toBe(2250)
        ->and($refund->abs()->minor)->toBe(1250)
        ->and($refund->negate()->minor)->toBe(1250)
        ->and($refund->min($charge)->minor)->toBe(-1250)
        ->and($refund->max($charge)->minor)->toBe(1000)
        ->and($refund->multiply(-1)->minor)->toBe(1250)
        ->and($refund->percentage(10)->minor)->toBe(-125)
        ->and($refund->isNegative())->toBeTrue()
        ->and($refund->isPositive())->toBeFalse()
        ->and(Money::zero()->isPositive())->toBeFalse()
        ->and(Money::zero()->isNegative())->toBeFalse()
        ->and(Money::ofMinor(-1)->toDecimal())->toBe('-0.01')
        ->and(Money::ofMinor(-100050)->toDecimal())->toBe('-1000.50');
});

it('refuses to mix currencies in every binary operation', function (string $operation): void {
    $usd = Money::ofMinor(100, 'USD');
    $sar = Money::ofMinor(100, 'SAR');

    $usd->{$operation}($sar);
})->with(['add', 'subtract', 'compare', 'min', 'max', 'greaterThan', 'lessThan'])
    ->throws(CurrencyMismatchException::class, 'Currency mismatch: expected [USD], got [SAR].');

it('checks every operand of a variadic add or subtract', function (): void {
    Money::ofMinor(100)->add(Money::ofMinor(1), Money::ofMinor(1, 'EUR'));
})->throws(CurrencyMismatchException::class);

it('treats equal amounts in different currencies as not equal without throwing', function (): void {
    expect(Money::ofMinor(100, 'USD')->equals(Money::ofMinor(100, 'SAR')))->toBeFalse()
        ->and(Money::ofMinor(100, 'usd')->equals(Money::ofMinor(100)))->toBeTrue();
});

it('is immutable', function (): void {
    $price = Money::ofMinor(1000);

    $price->add(Money::ofMinor(500));
    $price->subtract(Money::ofMinor(500));
    $price->multiply(2);
    $price->negate();

    expect($price->minor)->toBe(1000)->and($price->currency)->toBe('USD');
});

it('rejects non-numeric string amounts', function (string $amount): void {
    Money::of($amount);
})->with(['', 'abc', '12,50', '1.2.3'])->throws(InvalidArgumentException::class);

it('rejects malformed currency codes', function (string $currency): void {
    Money::ofMinor(1, $currency);
})->with(['US', 'USDT', '12A', ''])->throws(InvalidArgumentException::class);

it('normalises lower-case currency codes on the factories', function (): void {
    expect(Money::of('1.00', 'eur')->currency)->toBe('EUR')
        ->and(Money::zero('sar')->currency)->toBe('SAR');
});

it('handles large amounts without float drift', function (): void {
    $large = Money::of('9999999.99');

    expect($large->minor)->toBe(999999999)
        ->and($large->toDecimal())->toBe('9999999.99')
        ->and($large->add(Money::ofMinor(1))->toDecimal())->toBe('10000000.00')
        ->and($large->multiply(1.0)->minor)->toBe(999999999);
});

it('formats money for both interface locales', function (): void {
    expect(Money::ofMinor(123456)->format('en'))->toContain('1,234.56')
        ->and(Money::ofMinor(123456)->format('ar'))->not->toBe('')
        ->and(Money::ofMinor(-500)->format('en'))->toContain('5.00');
});
