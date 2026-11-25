<?php

namespace App\Modules\Localization\Http\Controllers\Storefront;

use App\Modules\Localization\Http\Requests\CitySuggestionsRequest;
use App\Modules\Localization\Http\Resources\CityResource;
use App\Modules\Localization\Http\Resources\CountryResource;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Country select + city suggestions of the canonical address form (store countries only).
 */
class CountryController extends Controller
{
    public function __construct(private readonly StoreCountries $countries) {}

    public function index(Request $request): JsonResponse
    {
        return JsonResponder::success(data: CountryResource::collection($this->countries->available())->resolve($request));
    }

    public function cities(CitySuggestionsRequest $request, int $country): JsonResponse
    {
        abort_unless($this->countries->isAvailable($country), 404);

        $cities = $this->countries->citySuggestions($country, $request->validated('q'));

        return JsonResponder::success(data: CityResource::collection($cities)->resolve($request));
    }
}
