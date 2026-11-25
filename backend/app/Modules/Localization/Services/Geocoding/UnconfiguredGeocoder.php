<?php

namespace App\Modules\Localization\Services\Geocoding;

use App\Modules\Localization\DTOs\GeocodedLocationData;

/**
 * Used while Admin › Integrations › Geocoding is off (decision #69): no external call,
 * every lookup is "nothing found" and the caller shows its not-configured state.
 */
final class UnconfiguredGeocoder implements IpLocator, ReverseGeocoder
{
    public function reverse(float $latitude, float $longitude, string $locale): ?GeocodedLocationData
    {
        return null;
    }

    public function locate(string $ip, string $locale): ?GeocodedLocationData
    {
        return null;
    }
}
