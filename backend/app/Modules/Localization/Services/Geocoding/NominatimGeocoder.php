<?php

namespace App\Modules\Localization\Services\Geocoding;

use App\Modules\Localization\DTOs\GeocodedLocationData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenStreetMap Nominatim `/reverse` (jsonv2, addressdetails). Called server-side only,
 * with an application User-Agent (usage policy) and results cached by GeocodingService.
 */
class NominatimGeocoder implements ReverseGeocoder
{
    public function reverse(float $latitude, float $longitude, string $locale): ?GeocodedLocationData
    {
        $query = array_filter([
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'zoom' => 18,
            'lat' => $latitude,
            'lon' => $longitude,
            'accept-language' => $locale,
            'email' => config('services.geocoding.nominatim.email'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            $response = Http::baseUrl(rtrim((string) config('services.geocoding.nominatim.url'), '/'))
                ->withUserAgent((string) config('services.geocoding.user_agent'))
                ->acceptJson()
                ->timeout((int) config('services.geocoding.timeout', 5))
                ->get('/reverse', $query);
        } catch (ConnectionException $exception) {
            Log::warning('Reverse geocoding failed', ['provider' => 'nominatim', 'error' => $exception->getMessage()]);

            return null;
        }

        $address = $response->json('address');

        if (! $response->successful() || ! is_array($address)) {
            return null;
        }

        $street = trim(implode(' ', array_filter([
            $this->first($address, ['house_number']),
            $this->first($address, ['road', 'pedestrian', 'footway', 'street']),
        ])));
        $district = $this->first($address, ['neighbourhood', 'suburb', 'quarter', 'city_district']);
        $line = implode(', ', array_filter([$street !== '' ? $street : null, $district]));
        $iso2 = $this->first($address, ['country_code']);

        return new GeocodedLocationData(
            addressLine1: $line !== '' ? $line : null,
            city: $this->first($address, ['city', 'town', 'village', 'municipality', 'county']),
            state: $this->first($address, ['state', 'region', 'province', 'state_district']),
            countryIso2: $iso2 !== null ? strtoupper($iso2) : null,
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private function first(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($address[$key]) && is_string($address[$key]) && trim($address[$key]) !== '') {
                return trim($address[$key]);
            }
        }

        return null;
    }
}
