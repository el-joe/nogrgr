<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\DTOs\StopPeriodData;
use App\Modules\Shipping\Enums\StopPeriodRecurrence;
use App\Modules\Shipping\Enums\Weekday;
use App\Modules\Shipping\Http\Requests\Admin\StopPeriodRequest;
use App\Modules\Shipping\Models\ShippingStopPeriod;
use App\Modules\Shipping\Services\ShippingCompanyService;
use App\Modules\Shipping\Services\StopPeriodService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Logistics › Shipping › Stop periods (ADM-033c, Figma 71:47208): month calendar,
 * periods list, company / country filter; create / edit in a modal; delete.
 */
class StopPeriodController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly StopPeriodService $periods,
        private readonly ShippingCompanyService $companies,
        private readonly CountryService $countries,
    ) {}

    public function index(Request $request): View
    {
        $month = $this->month($request->query('month'));
        $companyId = filter_var($request->query('company'), FILTER_VALIDATE_INT) ?: null;
        $countryId = filter_var($request->query('country'), FILTER_VALIDATE_INT) ?: null;
        $periods = $this->periods->listing($companyId, $countryId);

        return view('shipping::admin.stop-periods.index', [
            'month' => $month,
            'weeks' => $this->periods->calendar($month, $periods),
            'periods' => $periods,
            'companies' => $this->companies->activeOptions(),
            'countries' => $this->countries->options(),
            'companyId' => $companyId,
            'countryId' => $countryId,
        ]);
    }

    public function create(Request $request): View
    {
        $company = filter_var($request->query('company'), FILTER_VALIDATE_INT) ?: null;
        $today = CarbonImmutable::today();

        return $this->form($request, new ShippingStopPeriod([
            'recurrence' => StopPeriodRecurrence::Once,
            'is_active' => true,
            'starts_on' => $today,
            'shipping_company_id' => $company,
        ]));
    }

    public function store(StopPeriodRequest $request): JsonResponse
    {
        $period = $this->periods->create(StopPeriodData::fromArray($request->payload()));

        return JsonResponder::created(__('shipping::stop_periods.created', ['name' => $period->name]), redirect: $this->back($period));
    }

    public function edit(Request $request, ShippingStopPeriod $stopPeriod): View
    {
        return $this->form($request, $stopPeriod);
    }

    public function update(StopPeriodRequest $request, ShippingStopPeriod $stopPeriod): JsonResponse
    {
        $this->periods->update($stopPeriod, StopPeriodData::fromArray($request->payload()));

        return JsonResponder::success(__('shipping::stop_periods.updated', ['name' => $stopPeriod->name]), redirect: $this->back($stopPeriod));
    }

    public function destroy(ShippingStopPeriod $stopPeriod): JsonResponse
    {
        $this->periods->delete($stopPeriod);

        return JsonResponder::success(__('shipping::stop_periods.deleted', ['name' => $stopPeriod->name]), redirect: url()->previous(route('admin.shipping.stop-periods.index')));
    }

    private function back(ShippingStopPeriod $period): string
    {
        return route('admin.shipping.stop-periods.index', ['month' => $period->starts_on->format('Y-m')]);
    }

    private function month(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    private function form(Request $request, ShippingStopPeriod $period): View
    {
        $title = __($period->exists ? 'shipping::stop_periods.edit_title' : 'shipping::stop_periods.create_title');
        $index = route('admin.shipping.stop-periods.index');

        return $this->remoteModal($request, 'shipping::admin.stop-periods._form', [
            'period' => $period,
            'title' => $title,
            'companies' => $this->companies->activeOptions(),
            'countries' => $this->countries->options(),
            'recurrences' => StopPeriodRecurrence::options(),
            'weekdays' => Weekday::options(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            pageModule: 'admin-shipping',
            formId: 'stop-period-form',
            submitLabel: __('shipping::common.save'),
            breadcrumbs: [__('navigation.sections.logistics') => null, __('shipping::stop_periods.title') => $index, $title => null],
        ));
    }
}
