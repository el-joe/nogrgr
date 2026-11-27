<?php

namespace App\Modules\Localization\Http\Controllers\Admin;

use App\Modules\Localization\DataTables\CurrenciesDataTable;
use App\Modules\Localization\DTOs\CurrencyData;
use App\Modules\Localization\Enums\ActiveState;
use App\Modules\Localization\Http\Concerns\AnswersDomainErrors;
use App\Modules\Localization\Http\Requests\Admin\CurrencyRequest;
use App\Modules\Localization\Http\Requests\Admin\ToggleActiveRequest;
use App\Modules\Localization\Jobs\SyncExchangeRates;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Services\CurrencyService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Localization › Currencies (ADM-095 / 095a): list with last sync, create /
 * edit, active switch, delete and "Sync rates now". USD (base) is locked.
 */
class CurrencyController extends Controller
{
    use AnswersDomainErrors;

    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): View
    {
        return view('localization::admin.currencies.index', [
            'tab' => $request->query('tab') === 'all' ? 'all' : ActiveState::Active->value,
            'counts' => $this->currencies->counts(),
            'lastSyncedAt' => $this->currencies->lastSyncedAt(),
            'nextSyncAt' => $this->currencies->nextSyncAt(),
            'unsynced' => $this->currencies->unsyncedActiveCodes(),
        ]);
    }

    public function data(Request $request, CurrenciesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, CurrenciesDataTable::FILTERS);
    }

    public function create(): View
    {
        return view('localization::admin.currencies.form', ['currency' => null]);
    }

    public function store(CurrencyRequest $request): JsonResponse
    {
        $currency = $this->currencies->create(CurrencyData::fromArray($request->payload()));

        return JsonResponder::created(__('localization::admin.currencies.created', ['name' => $currency->name]), redirect: route('admin.localization.currencies.index', ['tab' => 'all']));
    }

    public function edit(Currency $currency): View
    {
        return view('localization::admin.currencies.form', ['currency' => $currency]);
    }

    public function update(CurrencyRequest $request, Currency $currency): JsonResponse
    {
        return $this->attempt(
            fn () => $this->currencies->update($currency, CurrencyData::fromArray($request->payload())),
            __('localization::admin.currencies.updated', ['name' => $currency->name]),
            route('admin.localization.currencies.index'),
        );
    }

    public function toggle(ToggleActiveRequest $request, Currency $currency): JsonResponse
    {
        return $this->attempt(
            fn () => $this->currencies->setActive($currency, $request->active()),
            __($request->active() ? 'localization::admin.currencies.activated' : 'localization::admin.currencies.deactivated', ['name' => $currency->name]),
        );
    }

    public function destroy(Currency $currency): JsonResponse
    {
        return $this->attempt(
            fn () => $this->currencies->delete($currency),
            __('localization::admin.currencies.deleted', ['name' => $currency->name]),
        );
    }

    public function sync(): JsonResponse
    {
        SyncExchangeRates::dispatch();

        return JsonResponder::success(__('localization::admin.currencies.sync_queued'));
    }
}
