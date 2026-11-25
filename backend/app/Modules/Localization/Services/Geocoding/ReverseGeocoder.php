<?php

namespace App\Modules\Localization\Services\Geocoding;

use App\Modules\Localization\DTOs\GeocodedLocationData;

/**
 * Point → address provider. Returns null when nothing was found or the provider failed.
 */
interface ReverseGeocoder
{
    public function reverse(float $latitude, float $longitude, string $locale): ?GeocodedLocationData;
}
