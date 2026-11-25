<?php

namespace App\Modules\Localization\Http\Controllers\Admin;

use App\Modules\Localization\DataTables\LanguagePurchasesDataTable;
use App\Modules\Localization\Services\LanguagePurchaseService;
use App\Modules\Localization\Services\LanguageService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Localization › Languages › Purchases (ADM-094, Figma 79:83875): language and AI
 * translation add-on purchases of every store with type / status / gateway filters,
 * and the purchase detail (invoice + payment attempts). Read-only; Billing owns the data.
 */
class LanguagePurchaseController extends Controller
{
    public function __construct(
        private readonly LanguagePurchaseService $purchases,
        private readonly LanguageService $languages,
    ) {}

    public function index(): View
    {
        return view('localization::admin.purchases.index', [
            'counts' => $this->languages->counts() + ['purchases' => $this->purchases->count()],
        ]);
    }

    public function data(Request $request, LanguagePurchasesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, LanguagePurchasesDataTable::FILTERS);
    }

    public function show(int $purchase): View
    {
        $record = $this->purchases->find($purchase);

        return view('localization::admin.purchases.show', [
            'purchase' => $record,
            'description' => $this->purchases->describe($record),
            'logs' => $this->purchases->paymentLogs($record),
        ]);
    }
}
