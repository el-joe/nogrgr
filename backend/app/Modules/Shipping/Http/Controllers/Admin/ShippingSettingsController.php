<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\DTOs\ShippingSettingsData;
use App\Modules\Shipping\Enums\Weekday;
use App\Modules\Shipping\Http\Requests\Admin\DefaultCompanyRequest;
use App\Modules\Shipping\Http\Requests\Admin\ShippingSettingsRequest;
use App\Modules\Shipping\Services\ShippingCompanyService;
use App\Modules\Shipping\Services\ShippingSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Logistics › Shipping › Settings (ADM-034, Figma 71:48071): defaults (min / max
 * days, cut-off, weekly off days, storefront toggles) and the default company per country.
 */
class ShippingSettingsController extends Controller
{
    public function __construct(
        private readonly ShippingSettingsService $settings,
        private readonly ShippingCompanyService $companies,
        private readonly CountryService $countries,
        private readonly CountryRepositoryContract $countryRecords,
    ) {}

    public function edit(): View
    {
        $defaults = $this->settings->defaultsTable();
        $countryNames = $this->countryRecords->query()->whereIn('id', array_column($defaults, 'country_id'))->get()->keyBy('id');

        return view('shipping::admin.settings.edit', [
            'settings' => $this->settings->get(),
            'defaults' => $defaults,
            'countryNames' => $countryNames,
            'weekdays' => Weekday::options(),
            'countries' => $this->countries->options(),
            'companies' => $this->companies->activeOptions(),
        ]);
    }

    public function update(ShippingSettingsRequest $request): JsonResponse
    {
        $this->settings->update(ShippingSettingsData::fromArray($request->payload()));

        return JsonResponder::success(__('shipping::settings.saved'));
    }

    public function storeDefault(DefaultCompanyRequest $request): JsonResponse
    {
        $this->settings->setDefaultCompany((int) $request->validated('country_id'), (int) $request->validated('shipping_company_id'));

        return JsonResponder::success(__('shipping::settings.defaults.saved'), redirect: route('admin.shipping.settings'));
    }

    public function destroyDefault(Country $country): JsonResponse
    {
        $this->settings->setDefaultCompany($country->id, null);

        return JsonResponder::success(__('shipping::settings.defaults.removed', ['country' => $country->name]), redirect: route('admin.shipping.settings'));
    }
}
