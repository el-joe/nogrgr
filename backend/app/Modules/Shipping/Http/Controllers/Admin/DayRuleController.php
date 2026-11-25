<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\DTOs\DayRuleData;
use App\Modules\Shipping\Http\Concerns\AnswersDomainErrors;
use App\Modules\Shipping\Http\Requests\Admin\DayRuleRequest;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingDayRule;
use App\Modules\Shipping\Services\ShippingCompanyService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company › Day rules tab (ADM-033b): add / edit (modal) and delete a country row
 * {min days, max days, default company for the country}.
 */
class DayRuleController extends Controller
{
    use AnswersDomainErrors;
    use RendersRemoteModals;

    public function __construct(
        private readonly ShippingCompanyService $companies,
        private readonly CountryService $countries,
    ) {}

    public function create(Request $request, ShippingCompany $company): View
    {
        return $this->form($request, $company, new ShippingDayRule(['min_days' => 1, 'max_days' => 3]));
    }

    public function store(DayRuleRequest $request, ShippingCompany $company): JsonResponse
    {
        return $this->attempt(
            fn () => $this->companies->saveDayRule($company, DayRuleData::fromArray($request->payload())),
            __('shipping::companies.day_rules.saved'),
            $this->back($company),
            created: true,
        );
    }

    public function edit(Request $request, ShippingCompany $company, int $dayRule): View
    {
        return $this->form($request, $company, $this->companies->findDayRule($company, $dayRule));
    }

    public function update(DayRuleRequest $request, ShippingCompany $company, int $dayRule): JsonResponse
    {
        $rule = $this->companies->findDayRule($company, $dayRule);

        return $this->attempt(
            fn () => $this->companies->saveDayRule($company, DayRuleData::fromArray($request->payload()), $rule),
            __('shipping::companies.day_rules.saved'),
            $this->back($company),
        );
    }

    public function destroy(ShippingCompany $company, int $dayRule): JsonResponse
    {
        $rule = $this->companies->findDayRule($company, $dayRule);

        return $this->attempt(fn () => $this->companies->deleteDayRule($company, $rule), __('shipping::companies.day_rules.deleted'), $this->back($company));
    }

    private function back(ShippingCompany $company): string
    {
        return route('admin.shipping.companies.edit', [$company, 'tab' => 'day-rules']);
    }

    private function form(Request $request, ShippingCompany $company, ShippingDayRule $rule): View
    {
        $title = __($rule->exists ? 'shipping::companies.day_rules.edit_title' : 'shipping::companies.day_rules.create_title');

        return $this->remoteModal($request, 'shipping::admin.companies._day-rule-form', [
            'company' => $company,
            'rule' => $rule,
            'title' => $title,
            'countries' => $this->countries->options(),
            'isDefault' => $rule->exists && in_array($rule->country_id, $this->companies->defaultCountryIds($company), true),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $this->back($company),
            pageModule: 'admin-shipping',
            formId: 'day-rule-form',
            submitLabel: __('shipping::common.save'),
            breadcrumbs: [__('navigation.sections.logistics') => null, __('shipping::companies.title') => route('admin.shipping.companies.index'), $company->name => $this->back($company), $title => null],
        ));
    }
}
