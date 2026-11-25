<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\Http\Concerns\AnswersDomainErrors;
use App\Modules\Shipping\Http\Requests\Admin\DeliveryPopupRequest;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\DeliveryPopupService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Logistics › Shipping › Delivery popup days (ADM-035, Figma 54:19015 / 54:19898):
 * days 1–10 with a % each per country (or all countries), total exactly 100 %, live
 * storefront preview with the delivery dates of the chosen country.
 */
class DeliveryPopupController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly DeliveryPopupService $popup,
        private readonly DeliveryEstimateService $estimates,
        private readonly CountryService $countries,
        private readonly CountryRepositoryContract $countryRecords,
    ) {}

    public function index(Request $request): View
    {
        $countryId = filter_var($request->query('country'), FILTER_VALIDATE_INT) ?: null;
        /** @var Country|null $country */
        $country = $countryId !== null ? $this->countryRecords->find($countryId) : null;
        $previewCountry = $country ?? $this->countries->active()->first();
        $distribution = $this->popup->distribution($country?->id);

        return view('shipping::admin.delivery-popup.index', [
            'country' => $country,
            'countries' => $this->countries->options(),
            'customised' => $this->popup->customisedCountryIds(),
            'percentages' => $distribution['percentages'],
            'own' => $distribution['own'],
            'previewCountry' => $previewCountry,
            'previewDates' => $previewCountry !== null ? $this->estimates->deliveryDays($previewCountry->id, DeliveryPopupService::DAYS, CarbonImmutable::now()) : [],
        ]);
    }

    public function update(DeliveryPopupRequest $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->popup->save($request->countryId(), $request->percentages()),
            __('shipping::popup.saved'),
        );
    }

    public function reset(Request $request): JsonResponse
    {
        $countryId = filter_var($request->input('country_id'), FILTER_VALIDATE_INT) ?: null;
        $this->popup->reset($countryId);

        return JsonResponder::success(__('shipping::popup.reset_done'), redirect: route('admin.shipping.delivery-popup.index', array_filter(['country' => $countryId])));
    }
}
