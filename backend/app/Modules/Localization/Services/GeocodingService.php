<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\DTOs\GeocodedLocationData;
use App\Modules\Localization\Services\Geocoding\IpLocator;
use App\Modules\Localization\Services\Geocoding\ReverseGeocoder;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side address lookup for the address form's "Select address on map":
 *
 *  - reverse(): lat/lng → street, city, state and the store country (provider from
 *    config('services.geocoding.reverse_provider'), cached per rounded point + language)
 *  - locateIp(): visitor IP → approximate city / state / country (cached per IP);
 *    private addresses and provider failures fall back to the visitor country header /
 *    config('app.default_country').
 *
 * The country is only returned as `countryId` when the store sells to it.
 */
class GeocodingService extends BaseService
{
    public function __construct(
        private readonly ReverseGeocoder $geocoder,
        private readonly IpLocator $ipLocator,
        private readonly StoreCountries $countries,
    ) {}

    public function reverse(float $latitude, float $longitude, string $locale): ?GeocodedLocationData
    {
        $key = sprintf('geocoding:reverse:%s:%.5f:%.5f', $locale, $latitude, $longitude);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        if ($cached === null) {
            $found = $this->geocoder->reverse($latitude, $longitude, $locale);

            if ($found === null) {
                return null;
            }

            $cached = $found->toArray();
            Cache::put($key, $cached, (int) config('services.geocoding.cache_ttl'));
        }

        return $this->withStoreCountry(GeocodedLocationData::fromArray($cached));
    }

    public function locateIp(?string $ip, string $locale): GeocodedLocationData
    {
        $found = null;

        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            $key = 'geocoding:ip:'.$locale.':'.hash('sha256', $ip);

            /** @var array<string, mixed>|null $cached */
            $cached = Cache::get($key);

            if ($cached === null) {
                $cached = $this->ipLocator->locate($ip, $locale)?->toArray();

                if ($cached !== null) {
                    Cache::put($key, $cached, (int) config('services.geocoding.cache_ttl'));
                }
            }

            $found = $cached !== null ? GeocodedLocationData::fromArray($cached) : null;
        }

        $found ??= new GeocodedLocationData(null, null, null, strtoupper(Locale::visitorCountry()), null, null, approximate: true);

        return $this->withStoreCountry($found);
    }

    private function withStoreCountry(GeocodedLocationData $location): GeocodedLocationData
    {
        $country = $location->countryIso2 !== null ? $this->countries->findByIso2($location->countryIso2) : null;

        return $location->with(['country_id' => $country?->id]);
    }
}
