<?php

namespace App\Modules\Localization\Services\Geocoding;

use App\Modules\Localization\DTOs\GeocodedLocationData;

/**
 * IP address → approximate location provider. Returns null when unknown or failed.
 */
interface IpLocator
{
    public function locate(string $ip, string $locale): ?GeocodedLocationData;
}
