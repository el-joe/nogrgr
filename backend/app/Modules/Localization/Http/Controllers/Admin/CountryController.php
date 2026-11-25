<?php

namespace App\Modules\Localization\Http\Controllers\Admin;

use App\Modules\Localization\DataTables\CountriesDataTable;
use App\Modules\Localization\DTOs\CountryData;
use App\Modules\Localization\Enums\ActiveState;
use App\Modules\Localization\Http\Concerns\AnswersDomainErrors;
use App\Modules\Localization\Http\Requests\Admin\CountryRequest;
use App\Modules\Localization\Http\Requests\Admin\ToggleActiveRequest;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Localization\Services\CurrencyService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Localization › Countries (ADM-096): ISO list, edit and active switch.
 */
class CountryController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly CountryService $countries,
        private readonly CurrencyService $currencies,
    ) {}

    public function index(Request $request): View
    {
        return view('localization::admin.countries.index', [
            'tab' => $request->query('tab') === 'all' ? 'all' : ActiveState::Active->value,
            'counts' => $this->countries->counts(),
        ]);
    }

    public function data(Request $request, CountriesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, CountriesDataTable::FILTERS);
    }

    public function edit(Country $country): View
    {
        return view('localization::admin.countries.form', [
            'country' => $country,
            'currencies' => $this->currencies->options(),
        ]);
    }

    public function update(CountryRequest $request, Country $country): JsonResponse
    {
        return $this->attempt(
            fn () => $this->countries->update($country, CountryData::fromArray($request->payload())),
            __('localization::admin.countries.updated', ['name' => $country->name]),
            route('admin.localization.countries.index'),
        );
    }

    public function toggle(ToggleActiveRequest $request, Country $country): JsonResponse
    {
        return $this->attempt(
            fn () => $this->countries->setActive($country, $request->active()),
            __($request->active() ? 'localization::admin.countries.activated' : 'localization::admin.countries.deactivated', ['name' => $country->name]),
        );
    }
}
