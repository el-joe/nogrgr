<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\DataTables\UiKitProductsDataTable;
use App\Modules\Platform\Http\Requests\Admin\UiKitFormRequest;
use App\Modules\Platform\Support\UiKitSamples;
use App\Shared\DataTables\DataTableFilters;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Support\Icon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Component library (x-ui::*) with every component and state, light/dark and
 * LTR/RTL — for designers, developers and QA (permission system.ui-kit.view).
 */
class UiKitController extends Controller
{
    public function show(Request $request): View
    {
        return view('platform::admin.ui-kit', [
            'icons' => Icon::names(),
            'paginator' => UiKitSamples::paginator(max(1, $request->integer('page', 1))),
            'notifications' => UiKitSamples::notifications(),
            'lineRanges' => UiKitSamples::lineRanges(),
        ]);
    }

    public function table(Request $request, UiKitProductsDataTable $table): JsonResponse
    {
        return $table->toJson(DataTableFilters::fromRequest($request, ['status', 'search']));
    }

    public function bulk(Request $request): JsonResponse
    {
        $count = count((array) $request->input('ids', []));

        return JsonResponder::success(trans_choice('platform::ui-kit.bulk_done', $count, ['count' => $count]));
    }

    public function submit(UiKitFormRequest $request): JsonResponse
    {
        return JsonResponder::success(__('platform::ui-kit.form_saved'));
    }
}
