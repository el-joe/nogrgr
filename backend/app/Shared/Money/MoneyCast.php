<?php

namespace App\Shared\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts an integer minor-units column to Money.
 *
 * Usage in $casts / casts():
 *   'total' => MoneyCast::class                 // currency from the `currency` column
 *   'total' => MoneyCast::class.':order_currency' // currency from another column
 *   'total' => MoneyCast::class.':USD'           // fixed currency (3 upper-case letters)
 *
 * @implements CastsAttributes<Money, Money|int|null>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $currency = 'currency') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::ofMinor((int) $value, $this->resolveCurrency($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (is_int($value)) {
            return [$key => $value];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("[{$key}] must be a Money instance or integer minor units.");
        }

        $result = [$key => $value->minor];

        if (! $this->isFixedCurrency()) {
            $current = $attributes[$this->currency] ?? null;

            if ($current !== null && $current !== $value->currency) {
                throw new CurrencyMismatchException((string) $current, $value->currency);
            }

            $result[$this->currency] = $value->currency;
        } elseif ($value->currency !== $this->currency) {
            throw new CurrencyMismatchException($this->currency, $value->currency);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCurrency(array $attributes): string
    {
        if ($this->isFixedCurrency()) {
            return $this->currency;
        }

        $currency = $attributes[$this->currency] ?? null;

        if (! is_string($currency) || $currency === '') {
            throw new InvalidArgumentException("Currency column [{$this->currency}] is empty.");
        }

        return $currency;
    }

    private function isFixedCurrency(): bool
    {
        return preg_match('/^[A-Z]{3}$/', $this->currency) === 1;
    }
}
