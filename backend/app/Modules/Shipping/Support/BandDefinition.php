<?php

namespace App\Modules\Shipping\Support;

use App\Shared\Money\Money;

/**
 * Pure weight band: matches when min ≤ grams ≤ max (old PlacesOrders::rateMatchesWeight;
 * null max = open-ended).
 */
final readonly class BandDefinition
{
    public function __construct(
        public string $name,
        public int $minGrams,
        public ?int $maxGrams,
        public Money $price,
        public ?int $id = null,
    ) {}

    public function matches(int $grams): bool
    {
        return $grams >= $this->minGrams && ($this->maxGrams === null || $grams <= $this->maxGrams);
    }

    /**
     * Two bands overlap when their inclusive gram ranges share at least one gram.
     */
    public function overlaps(self $other): bool
    {
        $thisMax = $this->maxGrams ?? PHP_INT_MAX;
        $otherMax = $other->maxGrams ?? PHP_INT_MAX;

        return $this->minGrams <= $otherMax && $other->minGrams <= $thisMax;
    }

    public function rangeLabel(): string
    {
        return number_format($this->minGrams).'–'.($this->maxGrams === null ? '∞' : number_format($this->maxGrams)).' g';
    }
}
