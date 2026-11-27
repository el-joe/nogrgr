<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Platform\DataTables\UiKitProductsDataTable;
use App\Modules\Platform\Http\Requests\Vendor\VendorUiKitFormRequest;
use App\Modules\Platform\Support\UiKitSamples;
use App\Shared\DataTables\DataTableFilters;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor UI kit (VEN-093, decision #116, aligned kit 191:30163): every vendor-kit
 * component in the Tenant Panel language, light / dark and LTR / RTL. Internal only —
 * gate vendor.ui-kit.view (impersonating platform admins or the internal ui-kit.view).
 */
class UiKitController extends Controller
{
    public function show(Request $request): View
    {
        return view('platform::vendor.ui-kit.show', [
            'paginator' => UiKitSamples::paginator(max(1, $request->integer('page', 1))),
            'lineRanges' => UiKitSamples::lineRanges(),
        ]);
    }

    public function table(Request $request, UiKitProductsDataTable $table): JsonResponse
    {
        return $table->toJson(DataTableFilters::fromRequest($request, ['status', 'search']), 'platform::vendor.ui-kit._actions-cell');
    }

    public function submit(VendorUiKitFormRequest $request): JsonResponse
    {
        return JsonResponder::success(__('platform::vendor_ui_kit.form_saved'));
    }
}
