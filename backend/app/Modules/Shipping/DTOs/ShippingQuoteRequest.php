<?php

namespace App\Modules\Shipping\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Input of ShippingQuoteService: destination (country, optional city or explicit zone)
 * and the lines.
 */
final class ShippingQuoteRequest extends BaseDTO
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public readonly ?int $countryId,
        public readonly array $lines,
        public readonly ?int $cityId = null,
        public readonly ?int $zoneId = null,
    ) {}

    /**
     * Quote for a single total weight (product pages, the vendor shipping-details modal).
     */
    public static function forWeight(?int $countryId, int $weightGrams, ?int $cityId = null, ?int $zoneId = null): self
    {
        return new self($countryId, [new QuoteLine('weight', $weightGrams)], $cityId, $zoneId);
    }
}
