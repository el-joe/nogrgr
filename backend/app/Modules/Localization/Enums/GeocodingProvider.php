<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Reverse-geocoding (lat/lng → address) and IP-location providers
 * (config/services.php `geocoding`).
 */
enum GeocodingProvider: string
{
    use HasEnumHelpers;

    /** OpenStreetMap Nominatim reverse geocoding. */
    case Nominatim = 'nominatim';

    /** ipwho.is IP location (approximate country / city). */
    case IpWhoIs = 'ipwhois';
}
