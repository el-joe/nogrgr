<?php

namespace App\Modules\Localization\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Http\Requests\Vendor\UpdateStoreCurrenciesRequest;
use App\Modules\Localization\Services\StoreCurrencyService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Currencies (VEN-072, Figma Vendor Panel 90:112623): the store's
 * shopper-facing currencies with the platform rate (1 USD =), the countries they are shown
 * in, "Default" and "Active" switches. Display only — checkout always charges USD.
 * Permissions: settings.currencies.view (page) / settings.currencies.manage (save).
 */
class CurrencySettingsController extends Controller
{
    public function __construct(private readonly StoreCurrencyService $currencies) {}

    public function index(Request $request): View
    {
        $staff = $request->user('vendor');

        return view('localization::vendor.currencies', [
            'rows' => $this->currencies->rows(),
            'syncedAt' => $this->currencies->lastSyncedAt(),
            'canManage' => $staff instanceof VendorStaff && $staff->can('settings.currencies.manage'),
        ]);
    }

    public function update(UpdateStoreCurrenciesRequest $request): JsonResponse
    {
        $this->currencies->save($request->toData());

        return JsonResponder::success(__('localization::vendor_currencies.saved'), redirect: vendor_route('settings.currencies'));
    }
}
