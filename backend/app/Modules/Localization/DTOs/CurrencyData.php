<?php

namespace App\Modules\Localization\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a currency. `rate` = units per 1 USD (manual override until
 * the next sync); ignored for the USD base currency.
 */
final class CurrencyData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name  locale => name
     */
    public function __construct(
        public readonly string $code,
        public readonly array $name,
        public readonly string $symbol,
        public readonly int $decimals,
        public readonly ?string $rate,
        public readonly bool $isActive,
        public readonly int $sortOrder = 0,
    ) {}
}
