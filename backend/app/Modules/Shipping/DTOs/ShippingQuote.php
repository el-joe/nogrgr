<?php

namespace App\Modules\Shipping\DTOs;

use App\Modules\Shipping\Enums\ShippingUnavailableReason;
use App\Shared\Money\Money;

/**
 * Result of ShippingQuoteService (order.shipping_charge and its split over the lines).
 * Unavailable quotes carry a reason and a zero amount; an available quote without a
 * band is free.
 */
final readonly class ShippingQuote
{
    /**
     * @param  array<string, Money>  $lineFees  key => allocated share of the amount (Σ = amount)
     * @param  array<string, int>  $lineWeights  key => line weight in grams
     */
    public function __construct(
        public bool $available,
        public Money $amount,
        public int $totalWeightGrams,
        public array $lineFees = [],
        public array $lineWeights = [],
        public ?ShippingUnavailableReason $reason = null,
        public ?int $ruleId = null,
        public ?int $zoneId = null,
        public ?string $zoneName = null,
        public ?int $bandId = null,
        public ?string $bandName = null,
    ) {}

    public function isFree(): bool
    {
        return $this->available && $this->amount->isZero();
    }

    public function message(): ?string
    {
        return $this->reason?->message();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'amount' => $this->amount->minor,
            'amount_formatted' => $this->amount->format(),
            'free' => $this->isFree(),
            'total_weight_grams' => $this->totalWeightGrams,
            'reason' => $this->reason?->value,
            'message' => $this->message(),
            'rule_id' => $this->ruleId,
            'zone_id' => $this->zoneId,
            'zone_name' => $this->zoneName,
            'band_id' => $this->bandId,
            'band_name' => $this->bandName,
            'line_fees' => array_map(static fn (Money $fee): int => $fee->minor, $this->lineFees),
            'line_weights' => $this->lineWeights,
        ];
    }
}
