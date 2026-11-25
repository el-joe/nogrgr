<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\DataTables\TenantChangeRequestsDataTable;
use App\Modules\Catalog\Enums\TenantChangeStatus;
use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Catalog\Http\Requests\Admin\Requests\TenantChangeDecisionRequest;
use App\Modules\Catalog\Services\Requests\RequestsCenterService;
use App\Modules\Catalog\Services\Requests\TenantChangeService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Requests center › Tenant changes (ADM-018i): selling countries / categories; review page
 * with the current vs requested lists, approve (applies to the store) or reject, with a note.
 * Permissions: requests.tenant-changes.view / .manage.
 */
class TenantChangeRequestController extends Controller
{
    public function __construct(
        private readonly TenantChangeService $changes,
        private readonly RequestsCenterService $center,
    ) {}

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return view('catalog::admin.requests.tenant-changes.index', [
            'tabs' => $this->center->tabs($admin),
            'types' => TenantChangeType::options(),
            'statuses' => TenantChangeStatus::options(),
        ]);
    }

    public function data(Request $request, TenantChangeRequestsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, TenantChangeRequestsDataTable::FILTERS);
    }

    public function show(Request $request, int $id): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $change = $this->changes->findOrFail($id);
        $change->loadMissing(['tenant', 'reviewer']);

        return view('catalog::admin.requests.tenant-changes.show', [
            'request' => $change,
            'current' => $this->changes->names($change->type, array_map('intval', $change->current_ids)),
            'requested' => $this->changes->names($change->type, array_map('intval', $change->requested_ids)),
            'added' => $change->addedIds(),
            'removed' => $change->removedIds(),
            'canManage' => $admin->can('requests.tenant-changes.manage'),
        ]);
    }

    public function decide(TenantChangeDecisionRequest $request, int $id): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $change = $this->changes->findOrFail($id);

        if ($request->approves()) {
            $this->changes->approve($change, $admin, $request->note());

            return JsonResponder::success(__('catalog::requests.tenant_changes.approved'));
        }

        $this->changes->reject($change, $admin, (string) $request->note());

        return JsonResponder::success(__('catalog::requests.tenant_changes.rejected'));
    }
}
