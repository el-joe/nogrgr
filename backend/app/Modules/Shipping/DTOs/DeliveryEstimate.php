<?php

namespace App\Modules\Shipping\DTOs;

use Carbon\CarbonImmutable;

/**
 * Result of DeliveryDateEstimator: the delivery date range and the days it skipped.
 */
final readonly class DeliveryEstimate
{
    /**
     * @param  list<array{date: string, reason: string|null}>  $skipped  stop / off days inside the window
     */
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $minDate,
        public CarbonImmutable $maxDate,
        public int $minDays,
        public int $maxDays,
        public array $skipped = [],
        public ?int $companyId = null,
        public ?string $companyName = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate->toDateString(),
            'min_date' => $this->minDate->toDateString(),
            'max_date' => $this->maxDate->toDateString(),
            'min_days' => $this->minDays,
            'max_days' => $this->maxDays,
            'skipped' => $this->skipped,
            'company_id' => $this->companyId,
            'company_name' => $this->companyName,
        ];
    }
}
