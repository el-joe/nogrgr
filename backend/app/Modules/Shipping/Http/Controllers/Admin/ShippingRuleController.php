<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Models\City;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Shipping\DataTables\ShippingRulesDataTable;
use App\Modules\Shipping\DTOs\ShippingRuleData;
use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Modules\Shipping\Http\Concerns\AnswersDomainErrors;
use App\Modules\Shipping\Http\Requests\Admin\CitySearchRequest;
use App\Modules\Shipping\Http\Requests\Admin\ShippingRuleRequest;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Services\ShippingRuleService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Logistics › Shipping › Rules & zones (ADM-032 / 032a, Figma 71:43578 / 71:44486):
 * server-side list; create / edit page with zones (cities) and weight bands (overlap
 * validated); delete.
 */
class ShippingRuleController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly ShippingRuleService $rules,
        private readonly CountryService $countries,
        private readonly StoreCountries $cities,
    ) {}

    public function index(): View
    {
        return view('shipping::admin.rules.index', [
            'counts' => $this->rules->counts(),
            'countries' => $this->countries->options(),
            'statuses' => ShippingRuleStatus::options(),
        ]);
    }

    public function data(Request $request, ShippingRulesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ShippingRulesDataTable::FILTERS);
    }

    public function create(): View
    {
        return $this->form(new ShippingRule(['status' => ShippingRuleStatus::Active]));
    }

    public function store(ShippingRuleRequest $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->rules->create(ShippingRuleData::fromArray($request->payload())),
            __('shipping::rules.created'),
            route('admin.shipping.rules.index'),
            created: true,
        );
    }

    public function edit(ShippingRule $rule): View
    {
        return $this->form($rule->load(['countries', 'zones.cities', 'zones.bands']));
    }

    public function update(ShippingRuleRequest $request, ShippingRule $rule): JsonResponse
    {
        return $this->attempt(
            fn () => $this->rules->update($rule, ShippingRuleData::fromArray($request->payload())),
            __('shipping::rules.updated', ['name' => $request->validated('name')]),
            route('admin.shipping.rules.edit', $rule),
        );
    }

    public function destroy(ShippingRule $rule): JsonResponse
    {
        return $this->attempt(fn () => $this->rules->delete($rule), __('shipping::rules.deleted', ['name' => $rule->name]));
    }

    public function cities(CitySearchRequest $request): JsonResponse
    {
        $results = [];
        $term = $request->validated('q');
        $countryIds = $request->countryIds();

        foreach (array_slice($countryIds, 0, 20) as $countryId) {
            foreach ($this->cities->citySuggestions($countryId, is_string($term) ? $term : null, 30) as $city) {
                /** @var City $city */
                $results[] = ['id' => $city->id, 'text' => (string) $city->name];
            }
        }

        return response()->json(['results' => array_slice($results, 0, 50), 'pagination' => ['more' => false]]);
    }

    private function form(ShippingRule $rule): View
    {
        return view('shipping::admin.rules.form', [
            'rule' => $rule,
            'countries' => $this->countries->options(),
            'statuses' => ShippingRuleStatus::options(),
        ]);
    }
}
