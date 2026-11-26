<?php

namespace App\Modules\Engagement\DTOs;

/**
 * Approved-review summary of one product: average (1 decimal), count, per-star counts.
 */
final readonly class ReviewSummaryData
{
    /**
     * @param  array<int, int>  $distribution  5..1 => count (all five keys present)
     */
    public function __construct(
        public float $average,
        public int $count,
        public array $distribution,
    ) {}

    /**
     * @param  array<int, int>  $histogram  star => count (missing stars = 0)
     */
    public static function fromHistogram(array $histogram): self
    {
        $distribution = [];
        $count = 0;
        $sum = 0;

        foreach ([5, 4, 3, 2, 1] as $star) {
            $distribution[$star] = (int) ($histogram[$star] ?? 0);
            $count += $distribution[$star];
            $sum += $star * $distribution[$star];
        }

        return new self($count > 0 ? round($sum / $count, 1) : 0.0, $count, $distribution);
    }

    /**
     * @return array{average: float, count: int, distribution: array<int, int>}
     */
    public function toArray(): array
    {
        return ['average' => $this->average, 'count' => $this->count, 'distribution' => $this->distribution];
    }
}
