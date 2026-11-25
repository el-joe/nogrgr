<?php

namespace App\Modules\Localization\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Address parts found for a point (reverse geocoding) or an IP address. `countryId`
 * is the central country (null when the provider's country is unknown or inactive);
 * `approximate` is true for IP lookups ("Approximate location detected…").
 */
final class GeocodedLocationData extends BaseDTO
{
    public function __construct(
        public readonly ?string $addressLine1,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $countryIso2,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly bool $approximate = false,
        public readonly ?int $countryId = null,
    ) {}
}
