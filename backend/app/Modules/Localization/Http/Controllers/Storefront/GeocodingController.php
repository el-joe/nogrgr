<?php

namespace App\Modules\Localization\Http\Controllers\Storefront;

use App\Modules\Localization\DTOs\GeocodedLocationData;
use App\Modules\Localization\Http\Requests\ReverseGeocodeRequest;
use App\Modules\Localization\Services\GeocodingService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Select address on map": browser coordinates → address parts (reverse geocoding),
 * or the approximate location of the visitor's IP when geolocation is unavailable.
 */
class GeocodingController extends Controller
{
    public function __construct(private readonly GeocodingService $geocoding) {}

    public function reverse(ReverseGeocodeRequest $request): JsonResponse
    {
        if (! $this->geocoding->isConfigured()) {
            return JsonResponder::error(__('localization::geo.not_configured'), 503);
        }

        $location = $this->geocoding->reverse((float) $request->validated('lat'), (float) $request->validated('lng'), app()->getLocale());

        if ($location === null) {
            return JsonResponder::error(__('localization::geo.not_found'), 404);
        }

        return JsonResponder::success(data: $this->present($location));
    }

    public function ip(Request $request): JsonResponse
    {
        $location = $this->geocoding->locateIp($request->ip(), app()->getLocale());

        return JsonResponder::success(__('localization::geo.approximate'), $this->present($location));
    }

    /**
     * @return array{address_line_1: string|null, city: string|null, state: string|null, country_id: int|null, country_iso2: string|null, latitude: float|null, longitude: float|null, approximate: bool}
     */
    private function present(GeocodedLocationData $location): array
    {
        return [
            'address_line_1' => $location->addressLine1,
            'city' => $location->city,
            'state' => $location->state,
            'country_id' => $location->countryId,
            'country_iso2' => $location->countryIso2,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'approximate' => $location->approximate,
        ];
    }
}
