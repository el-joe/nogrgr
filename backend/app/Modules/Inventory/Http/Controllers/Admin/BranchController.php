<?php

namespace App\Modules\Inventory\Http\Controllers\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Inventory\DataTables\BranchesDataTable;
use App\Modules\Inventory\DataTables\BranchStockDataTable;
use App\Modules\Inventory\DTOs\BranchData;
use App\Modules\Inventory\Http\Concerns\AnswersDomainErrors;
use App\Modules\Inventory\Http\Requests\Admin\BranchRequest;
use App\Modules\Inventory\Http\Requests\Admin\BranchStockImportRequest;
use App\Modules\Inventory\Http\Requests\Admin\CitySearchRequest;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Services\BranchService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Services\ShippingRuleService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Logistics › Branches (ADM-030 / 030a, Figma 71:40880 / 71:41812): server-side
 * list, create / edit (details, fulfilment: default countries + shipping zones), stock table with
 * CSV import, delete.
 */
class BranchController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(
        private readonly BranchService $branches,
        private readonly StockService $stock,
        private readonly CountryService $countries,
        private readonly StoreCountries $cities,
        private readonly CatalogLocales $locales,
        private readonly ShippingRuleService $shippingRules,
    ) {}

    public function index(): View
    {
        return view('inventory::admin.branches.index', [
            'counts' => $this->branches->counts(),
            'countries' => $this->countries->options(),
        ]);
    }

    public function data(Request $request, BranchesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, BranchesDataTable::FILTERS);
    }

    public function create(): View
    {
        return $this->form(new Branch(['is_active' => true]));
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create(BranchData::fromArray($request->payload()));

        return JsonResponder::created(__('inventory::branches.created', ['name' => $branch->name]), redirect: route('admin.shipping.branches.edit', $branch));
    }

    public function edit(Branch $branch): View
    {
        return $this->form($branch->load(['city', 'zones.rule', 'defaultCountries']));
    }

    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $this->branches->update($branch, BranchData::fromArray($request->payload()));

        return JsonResponder::success(__('inventory::branches.updated', ['name' => $branch->name]), redirect: route('admin.shipping.branches.edit', $branch));
    }

    public function destroy(Branch $branch): JsonResponse
    {
        return $this->attempt(fn () => $this->branches->delete($branch), __('inventory::branches.deleted', ['name' => $branch->name]));
    }

    public function stock(Request $request, Branch $branch, BranchStockDataTable $table): JsonResponse
    {
        return $table->forBranch($branch->id)->toJsonFromRequest($request, BranchStockDataTable::FILTERS);
    }

    public function import(BranchStockImportRequest $request, Branch $branch): JsonResponse
    {
        $result = $this->stock->importForBranch($branch, $request->rows());
        $message = trans_choice('inventory::branches.stock.imported', $result['updated'], ['count' => $result['updated']]);

        if ($result['unknown'] !== []) {
            $message .= ' '.__('inventory::branches.stock.unknown', ['skus' => implode(', ', array_slice($result['unknown'], 0, 10))]);
        }

        return JsonResponder::success($message, $result, route('admin.shipping.branches.edit', [$branch, 'tab' => 'stock']));
    }

    public function cities(CitySearchRequest $request): JsonResponse
    {
        $cities = $this->cities->citySuggestions((int) $request->validated('country'), $request->validated('q'), 30);

        return response()->json([
            'results' => $cities->map(static fn (City $city): array => ['id' => $city->id, 'text' => (string) $city->name])->values()->all(),
            'pagination' => ['more' => false],
        ]);
    }

    /**
     * Select2 source of the "Serves zones" picker: shipping zones of the rules covering the country.
     */
    public function zones(CitySearchRequest $request): JsonResponse
    {
        $zones = $this->shippingRules->zonesForCountry((int) $request->validated('country'), $request->validated('q'));

        return response()->json([
            'results' => $zones->map(static fn (ShippingZone $zone): array => ['id' => $zone->id, 'text' => $zone->rule->name.' › '.$zone->name])->values()->all(),
            'pagination' => ['more' => false],
        ]);
    }

    private function form(Branch $branch): View
    {
        return view('inventory::admin.branches.form', [
            'branch' => $branch,
            'locales' => $this->locales->all(),
            'countries' => $this->countries->options(),
            'totals' => $branch->exists ? $this->branches->totals($branch) : ['skus' => 0, 'units' => 0],
        ]);
    }
}
