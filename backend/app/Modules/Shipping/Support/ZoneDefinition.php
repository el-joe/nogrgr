<?php

namespace App\Modules\Shipping\Support;

/**
 * Pure zone: the cities it covers (empty = the whole country) and its bands.
 */
final readonly class ZoneDefinition
{
    /**
     * @param  list<int>  $cityIds
     * @param  list<BandDefinition>  $bands
     */
    public function __construct(
        public string $name,
        public array $cityIds = [],
        public array $bands = [],
        public ?int $id = null,
    ) {}

    public function coversWholeCountry(): bool
    {
        return $this->cityIds === [];
    }

    /**
     * First matching band in (min, max) order; null = no band = free shipping.
     */
    public function bandFor(int $grams): ?BandDefinition
    {
        $bands = $this->bands;
        usort($bands, static fn (BandDefinition $a, BandDefinition $b): int => [$a->minGrams, $a->maxGrams ?? PHP_INT_MAX] <=> [$b->minGrams, $b->maxGrams ?? PHP_INT_MAX]);

        foreach ($bands as $band) {
            if ($band->matches($grams)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Pairs of band indexes (in input order) whose ranges overlap.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function overlappingBands(): array
    {
        $pairs = [];
        $count = count($this->bands);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->bands[$i]->overlaps($this->bands[$j])) {
                    $pairs[] = [$i, $j];
                }
            }
        }

        return $pairs;
    }
}
