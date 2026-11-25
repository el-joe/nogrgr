<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\DataTables\FixedShippingCostsDataTable;
use App\Modules\Shipping\DTOs\FixedShippingCostData;
use App\Modules\Shipping\Http\Requests\Admin\FixedShippingCalculateRequest;
use App\Modules\Shipping\Http\Requests\Admin\FixedShippingCostRequest;
use App\Modules\Shipping\Models\FixedShippingCost;
use App\Modules\Shipping\Services\FixedShippingCostService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Logistics › Shipping › Fixed costs (ADM-031 / 031a, Figma 71:42662 / 71:48842):
 * server-side list + calculator; create / edit in a modal (country unique); delete. Every
 * change queues the catalog fixed-shipping recompute.
 */
class FixedShippingCostController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly FixedShippingCostService $costs,
        private readonly CountryService $countries,
    ) {}

    public function index(): View
    {
        return view('shipping::admin.fixed-costs.index', [
            'countries' => $this->countries->options(),
            'configured' => count($this->costs->ratesByCountry()),
        ]);
    }

    public function data(Request $request, FixedShippingCostsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, FixedShippingCostsDataTable::FILTERS);
    }

    public function calculate(FixedShippingCalculateRequest $request): JsonResponse
    {
        $weight = (int) $request->validated('weight_grams');
        $sale = $request->salePrice();
        $result = $this->costs->calculate((int) $request->validated('country_id'), $weight, $sale);

        return response()->json([
            'configured' => $result['rate'] !== null,
            'shipping' => $result['shipping']->format(),
            'display' => $result['display']->format(),
            'text' => $result['rate'] === null
                ? __('shipping::fixed_costs.calculator.no_rate', ['price' => $sale->format()])
                : __('shipping::fixed_costs.calculator.result', [
                    'price' => $sale->format(),
                    'weight' => number_format($weight),
                    'rate' => '$'.FixedShippingCostsDataTable::rate($result['rate']),
                    'display' => $result['display']->format(),
                ]),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new FixedShippingCost);
    }

    public function store(FixedShippingCostRequest $request): JsonResponse
    {
        $this->costs->create(FixedShippingCostData::fromArray($request->payload()));

        return JsonResponder::created(__('shipping::fixed_costs.created'), redirect: route('admin.shipping.fixed-costs.index'));
    }

    public function edit(Request $request, FixedShippingCost $fixedCost): View
    {
        return $this->form($request, $fixedCost->load('country'));
    }

    public function update(FixedShippingCostRequest $request, FixedShippingCost $fixedCost): JsonResponse
    {
        $this->costs->update($fixedCost, FixedShippingCostData::fromArray($request->payload()));

        return JsonResponder::success(__('shipping::fixed_costs.updated'), redirect: route('admin.shipping.fixed-costs.index'));
    }

    public function destroy(FixedShippingCost $fixedCost): JsonResponse
    {
        $this->costs->delete($fixedCost);

        return JsonResponder::success(__('shipping::fixed_costs.deleted'));
    }

    private function form(Request $request, FixedShippingCost $cost): View
    {
        $title = $cost->exists ? __('shipping::fixed_costs.edit_title', ['country' => $cost->country->name]) : __('shipping::fixed_costs.create_title');
        $index = route('admin.shipping.fixed-costs.index');

        return $this->remoteModal($request, 'shipping::admin.fixed-costs._form', [
            'cost' => $cost,
            'title' => $title,
            'countries' => $this->countries->options(),
            'exampleWeight' => FixedShippingCostService::EXAMPLE_WEIGHT_GRAMS,
            'example' => $cost->exists ? $this->costs->shippingFor(FixedShippingCostService::EXAMPLE_WEIGHT_GRAMS, (string) $cost->price_per_gram) : null,
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            pageModule: 'admin-shipping',
            formId: 'fixed-cost-form',
            submitLabel: __('shipping::common.save'),
            breadcrumbs: [__('navigation.sections.logistics') => null, __('shipping::fixed_costs.title') => $index, $title => null],
        ));
    }
}
