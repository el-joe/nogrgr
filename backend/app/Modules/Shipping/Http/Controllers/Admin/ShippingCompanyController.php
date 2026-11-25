<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\DataTables\ShippingCompaniesDataTable;
use App\Modules\Shipping\DTOs\ShippingCompanyData;
use App\Modules\Shipping\Http\Requests\Admin\EstimateRequest;
use App\Modules\Shipping\Http\Requests\Admin\ShippingCompanyRequest;
use App\Modules\Shipping\Http\Requests\Admin\ToggleRequest;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\ShippingCompanyService;
use App\Modules\Shipping\Services\StopPeriodService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Logistics › Shipping › Companies (ADM-033 / 033a–d, Figma 71:45372 / 71:46296):
 * server-side list with active switch; company page with Details, Day rules and Stop
 * periods tabs and the live delivery estimator.
 */
class ShippingCompanyController extends Controller
{
    public function __construct(
        private readonly ShippingCompanyService $companies,
        private readonly StopPeriodService $stopPeriods,
        private readonly DeliveryEstimateService $estimates,
        private readonly CountryService $countries,
    ) {}

    public function index(): View
    {
        return view('shipping::admin.companies.index', [
            'counts' => $this->companies->counts(),
            'countries' => $this->countries->options(),
        ]);
    }

    public function data(Request $request, ShippingCompaniesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ShippingCompaniesDataTable::FILTERS);
    }

    public function create(): View
    {
        return $this->page(new ShippingCompany(['is_active' => true]), true);
    }

    public function store(ShippingCompanyRequest $request): JsonResponse
    {
        $company = $this->companies->create(ShippingCompanyData::fromArray($request->payload()));

        return JsonResponder::created(__('shipping::companies.created', ['name' => $company->name]), redirect: route('admin.shipping.companies.edit', $company));
    }

    public function show(ShippingCompany $company): View
    {
        return $this->page($company, false);
    }

    public function edit(ShippingCompany $company): View
    {
        return $this->page($company, true);
    }

    public function update(ShippingCompanyRequest $request, ShippingCompany $company): JsonResponse
    {
        $this->companies->update($company, ShippingCompanyData::fromArray($request->payload()));

        return JsonResponder::success(__('shipping::companies.updated', ['name' => $company->name]), redirect: route('admin.shipping.companies.edit', $company));
    }

    public function toggle(ToggleRequest $request, ShippingCompany $company): JsonResponse
    {
        $this->companies->setActive($company, $request->value());

        return JsonResponder::success(__($request->value() ? 'shipping::companies.activated' : 'shipping::companies.deactivated', ['name' => $company->name]));
    }

    public function destroy(ShippingCompany $company): JsonResponse
    {
        $this->companies->delete($company);

        return JsonResponder::success(__('shipping::companies.deleted', ['name' => $company->name]), redirect: route('admin.shipping.companies.index'));
    }

    public function estimate(EstimateRequest $request): JsonResponse
    {
        $estimate = $this->estimates->estimateFor(
            (int) $request->validated('country_id'),
            $request->filled('company_id') ? (int) $request->validated('company_id') : null,
            $request->orderDate(),
        );
        $format = static fn ($date): string => $date->locale(app()->getLocale())->isoFormat('ddd D MMM');
        $skipped = array_map(static fn (array $day): string => $day['reason'] !== null
            ? __('shipping::companies.estimator.skip_stop', ['date' => CarbonImmutable::parse($day['date'])->locale(app()->getLocale())->isoFormat('D MMM'), 'reason' => $day['reason']])
            : __('shipping::companies.estimator.skip_off', ['date' => CarbonImmutable::parse($day['date'])->locale(app()->getLocale())->isoFormat('ddd D')]), $estimate->skipped);

        return response()->json([
            ...$estimate->toArray(),
            'text' => __('shipping::companies.estimator.result', ['from' => $format($estimate->minDate), 'to' => $format($estimate->maxDate)]),
            'skipped_text' => $skipped === [] ? null : __('shipping::companies.estimator.skips', ['days' => implode(' + ', $skipped)]),
        ]);
    }

    private function page(ShippingCompany $company, bool $editable): View
    {
        $tab = in_array(request()->query('tab'), ['day-rules', 'stop-periods'], true) && $company->exists ? (string) request()->query('tab') : 'details';

        return view('shipping::admin.companies.form', [
            'company' => $company,
            'editable' => $editable && (bool) auth('admin')->user()?->can('shipping.companies.manage'),
            'tab' => $tab,
            'countries' => $this->countries->options(),
            'dayRules' => $company->exists ? $this->companies->dayRules($company) : collect(),
            'defaultCountryIds' => $company->exists ? $this->companies->defaultCountryIds($company) : [],
            'stopPeriods' => $company->exists ? $this->stopPeriods->listing($company->id) : collect(),
        ]);
    }
}
