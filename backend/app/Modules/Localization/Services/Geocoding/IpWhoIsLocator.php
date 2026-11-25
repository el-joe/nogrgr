<?php

namespace App\Modules\Localization\Services\Geocoding;

use App\Modules\Localization\DTOs\GeocodedLocationData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ipwho.is `/{ip}`: approximate city / region / country of the visitor's IP.
 */
class IpWhoIsLocator implements IpLocator
{
    public function locate(string $ip, string $locale): ?GeocodedLocationData
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.geocoding.ipwhois.url'), '/'))
                ->withUserAgent((string) config('services.geocoding.user_agent'))
                ->acceptJson()
                ->timeout((int) config('services.geocoding.timeout', 5))
                ->get('/'.rawurlencode($ip), ['lang' => $locale, 'fields' => 'success,city,region,country_code,latitude,longitude']);
        } catch (ConnectionException $exception) {
            Log::warning('IP location failed', ['provider' => 'ipwhois', 'error' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return null;
        }

        $iso2 = $response->json('country_code');
        $latitude = $response->json('latitude');
        $longitude = $response->json('longitude');

        return new GeocodedLocationData(
            addressLine1: null,
            city: is_string($response->json('city')) && $response->json('city') !== '' ? $response->json('city') : null,
            state: is_string($response->json('region')) && $response->json('region') !== '' ? $response->json('region') : null,
            countryIso2: is_string($iso2) && $iso2 !== '' ? strtoupper($iso2) : null,
            latitude: is_numeric($latitude) ? (float) $latitude : null,
            longitude: is_numeric($longitude) ? (float) $longitude : null,
            approximate: true,
        );
    }
}
