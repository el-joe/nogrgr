<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\DataTables\EditRequestsDataTable;
use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Http\Requests\Admin\Requests\EditRequestRejectRequest;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Services\Requests\EditRequestService;
use App\Modules\Catalog\Services\Requests\RequestsCenterService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Requests center › Product edit requests (ADM-018a/b): list with status filter, detail
 * with the field diff, approve (→ tenant override) and reject (reason required).
 * Permissions: requests.edits.view / requests.edits.manage.
 */
class EditRequestController extends Controller
{
    public function __construct(
        private readonly EditRequestService $editRequests,
        private readonly RequestsCenterService $center,
    ) {}

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return view('catalog::admin.requests.edits.index', [
            'tabs' => $this->center->tabs($admin),
            'statuses' => EditRequestStatus::options(),
        ]);
    }

    public function data(Request $request, EditRequestsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, EditRequestsDataTable::FILTERS);
    }

    public function show(Request $request, EditRequest $editRequest): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $editRequest->loadMissing(['tenant', 'product', 'variant', 'reviewer']);

        return view('catalog::admin.requests.edits.show', [
            'request' => $editRequest,
            'otherOverrides' => $this->editRequests->otherOverrides($editRequest),
            'service' => $this->editRequests,
            'canManage' => $admin->can('requests.edits.manage'),
        ]);
    }

    public function file(EditRequest $editRequest, int $index): StreamedResponse
    {
        return $this->editRequests->streamFile($editRequest, $index);
    }

    public function approve(Request $request, EditRequest $editRequest): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $this->editRequests->approve($editRequest, $admin);

        return JsonResponder::success(__('catalog::requests.edits.approved', ['reference' => $editRequest->reference(), 'store' => $editRequest->tenant?->getAttribute('name')]));
    }

    public function reject(EditRequestRejectRequest $request, EditRequest $editRequest): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $this->editRequests->reject($editRequest, $admin, $request->reason());

        return JsonResponder::success(__('catalog::requests.edits.rejected', ['reference' => $editRequest->reference()]));
    }
}
