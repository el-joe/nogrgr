<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\OptionSearchRequest;
use App\Modules\Platform\DataTables\MaintenanceWindowsDataTable;
use App\Modules\Platform\Enums\MaintenanceScope;
use App\Modules\Platform\Enums\MaintenanceWindowStatus;
use App\Modules\Platform\Http\Requests\Admin\MaintenanceWindowRequest;
use App\Modules\Platform\Models\MaintenanceWindow;
use App\Modules\Platform\Services\MaintenanceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › System tools › Maintenance windows (ADM-113): table, schedule / edit modal,
 * end now (cancel when scheduled) and delete. system.maintenance.view / .manage.
 */
class MaintenanceWindowController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function index(): View
    {
        return view('platform::admin.system.maintenance.index', [
            'next' => $this->maintenance->next(),
            'scopes' => MaintenanceScope::options(),
            'statuses' => MaintenanceWindowStatus::options(),
        ]);
    }

    public function data(Request $request, MaintenanceWindowsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, MaintenanceWindowsDataTable::FILTERS);
    }

    public function stores(OptionSearchRequest $request): JsonResponse
    {
        return response()->json($this->maintenance->storeOptions($request->term(), $request->page()));
    }

    public function store(MaintenanceWindowRequest $request): JsonResponse
    {
        $window = $this->maintenance->create($request->toData(), $this->adminId($request));

        return JsonResponder::created(__('platform::maintenance.created', ['title' => $window->title]));
    }

    public function update(MaintenanceWindowRequest $request, MaintenanceWindow $window): JsonResponse
    {
        $window = $this->maintenance->update($window, $request->toData());

        return JsonResponder::success(__('platform::maintenance.updated', ['title' => $window->title]));
    }

    public function end(Request $request, MaintenanceWindow $window): JsonResponse
    {
        $window = $this->maintenance->end($window, $this->adminId($request));

        return JsonResponder::success(__($window->status() === MaintenanceWindowStatus::Cancelled ? 'platform::maintenance.cancelled' : 'platform::maintenance.ended', ['title' => $window->title]));
    }

    public function destroy(MaintenanceWindow $window): JsonResponse
    {
        $this->maintenance->delete($window);

        return JsonResponder::success(__('platform::maintenance.deleted', ['title' => $window->title]));
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user('admin')?->getAuthIdentifier();

        return $id !== null ? (int) $id : null;
    }
}
