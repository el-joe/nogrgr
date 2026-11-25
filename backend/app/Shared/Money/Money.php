<?php

namespace App\Shared\Money;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;
use Stringable;

/**
 * Immutable money value: integer minor units + ISO-4217 currency.
 *
 * Scale is fixed at 2 decimals for every currency so results reconcile with
 * the old system (OrderPricing::money = round(v ± 1e-9, 2)). Do not change the
 * scale without re-running the ORDER_FORMULAS parity suite.
 */
final class Money implements JsonSerializable, Stringable
{
    /**
     * Platform base currency. Every stored amount (prices, orders, ledger,
     * subscriptions, payouts) is in USD; other currencies are display-only
     * conversions using the synced exchange rates.
     */
    public const BASE_CURRENCY = 'USD';

    public const SCALE = 2;

    private const FACTOR = 100;

    private const EPSILON = 1e-9;

    public function __construct(public readonly int $minor, public readonly string $currency)
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid currency code [{$currency}].");
        }
    }

    public static function ofMinor(int $minor, string $currency = self::BASE_CURRENCY): self
    {
        return new self($minor, strtoupper($currency));
    }

    /**
     * From a decimal major-unit amount, rounded exactly like the old OrderPricing::money().
     */
    public static function of(float|int|string $amount, string $currency = self::BASE_CURRENCY): self
    {
        if (is_string($amount) && ! is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return new self(self::toMinor(self::round2((float) $amount)), strtoupper($currency));
    }

    public static function zero(string $currency = self::BASE_CURRENCY): self
    {
        return new self(0, strtoupper($currency));
    }

    /**
     * Legacy rounding helper: round(v ± 1e-9, 2). Ported verbatim from
     * /var/www/multi-vendor/app/Support/OrderPricing.php::money().
     */
    public static function round2(float $value): float
    {
        return round($value + ($value >= 0 ? self::EPSILON : -self::EPSILON), self::SCALE);
    }

    public function add(self ...$others): self
    {
        $minor = $this->minor;

        foreach ($others as $other) {
            $this->assertSameCurrency($other);
            $minor += $other->minor;
        }

        return new self($minor, $this->currency);
    }

    public function subtract(self ...$others): self
    {
        $minor = $this->minor;

        foreach ($others as $other) {
            $this->assertSameCurrency($other);
            $minor -= $other->minor;
        }

        return new self($minor, $this->currency);
    }

    /**
     * Multiply by a factor, computed in major units and rounded with round2()
     * so it matches the legacy float formulas.
     */
    public function multiply(float|int $factor): self
    {
        if (is_int($factor)) {
            return new self($this->minor * $factor, $this->currency);
        }

        return new self(self::toMinor(self::round2($this->toFloat() * $factor)), $this->currency);
    }

    /**
     * Percentage of this amount, e.g. percentage(15) = 15 %.
     */
    public function percentage(float|int $percent): self
    {
        return new self(self::toMinor(self::round2($this->toFloat() * $percent / 100)), $this->currency);
    }

    public function divide(float|int $divisor): self
    {
        if ((float) $divisor === 0.0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return new self(self::toMinor(self::round2($this->toFloat() / $divisor)), $this->currency);
    }

    /**
     * Split into $parts amounts that always sum back to this amount
     * (remainder cents go to the first parts).
     *
     * @return list<self>
     */
    public function allocate(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Parts must be >= 1.');
        }

        $base = intdiv($this->minor, $parts);
        $remainder = $this->minor - ($base * $parts);
        $step = $remainder <=> 0;
        $result = [];

        for ($i = 0; $i < $parts; $i++) {
            $extra = $remainder !== 0 ? $step : 0;
            $remainder -= $extra;
            $result[] = new self($base + $extra, $this->currency);
        }

        return $result;
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function max(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor ? $this : $other;
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor <= $other->minor ? $this : $other;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function toFloat(): float
    {
        return $this->minor / self::FACTOR;
    }

    /**
     * Major units as a fixed 2-decimal string, e.g. "12.50".
     */
    public function toDecimal(): string
    {
        $sign = $this->minor < 0 ? '-' : '';
        $absolute = abs($this->minor);

        return $sign.intdiv($absolute, self::FACTOR).'.'.str_pad((string) ($absolute % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT);
    }

    public function format(?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($this->toFloat(), $this->currency);

        return $formatted === false ? $this->currency.' '.$this->toDecimal() : $formatted;
    }

    /**
     * @return array{amount: int, currency: string, decimal: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->minor, 'currency' => $this->currency, 'decimal' => $this->toDecimal()];
    }

    public function __toString(): string
    {
        return $this->currency.' '.$this->toDecimal();
    }

    private static function toMinor(float $rounded): int
    {
        return (int) round($rounded * self::FACTOR);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
