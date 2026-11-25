<?php

namespace App\Modules\Shipping\Support;

use Carbon\CarbonInterface;

/**
 * Pure stop-period definition used by the DeliveryDateEstimator: a date range
 * [startsOn, endsOn] (endsOn null = the start day only), or — when $weekday is set —
 * that weekday every week from startsOn until endsOn (null = forever).
 */
final readonly class StopPeriodRule
{
    public function __construct(
        public string $startsOn,
        public ?string $endsOn = null,
        public ?int $weekday = null,
        public ?string $name = null,
    ) {}

    public static function range(string $from, ?string $to = null, ?string $name = null): self
    {
        return new self($from, $to, null, $name);
    }

    public static function weekly(int $weekday, string $from = '1970-01-01', ?string $to = null, ?string $name = null): self
    {
        return new self($from, $to, $weekday, $name);
    }

    public function covers(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        if ($day < $this->startsOn) {
            return false;
        }

        if ($this->weekday !== null) {
            return $date->dayOfWeek === $this->weekday && ($this->endsOn === null || $day <= $this->endsOn);
        }

        return $day <= ($this->endsOn ?? $this->startsOn);
    }
}
