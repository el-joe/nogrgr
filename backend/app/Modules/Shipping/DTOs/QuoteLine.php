<?php

namespace App\Modules\Shipping\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * One cart / order line for a shipping quote. Weight is the unit weight in grams (variant
 * weight when > 0, else the product's). Own products are not shippable (weight 0, free,
 * excluded from every availability check — ORDER_FORMULAS.md §1).
 */
final class QuoteLine extends BaseDTO
{
    public function __construct(
        public readonly string $key,
        public readonly int $weightGrams,
        public readonly int $quantity = 1,
        public readonly bool $shippable = true,
    ) {}

    /**
     * Line weight in grams = unit weight × qty (0 for own products).
     */
    public function lineWeightGrams(): int
    {
        return $this->shippable ? max(0, $this->weightGrams) * max(0, $this->quantity) : 0;
    }
}
