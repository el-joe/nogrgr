<?php

namespace App\Modules\AfterSales\Http\Controllers\Vendor;

use App\Modules\AfterSales\DataTables\RefundsDataTable;
use App\Modules\AfterSales\DataTables\ReturnsDataTable;
use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Http\Requests\ManualRefundRequest;
use App\Modules\AfterSales\Http\Requests\RefundActionRequest;
use App\Modules\AfterSales\Http\Requests\ReturnActionRequest;
use App\Modules\AfterSales\Http\Requests\ReturnNoteRequest;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\RefundPageService;
use App\Modules\AfterSales\Services\RefundService;
use App\Modules\AfterSales\Services\ReturnPageService;
use App\Modules\AfterSales\Services\ReturnRequestService;
use App\Modules\AfterSales\Support\AfterSalesAccess;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor after-sales (tenant context, rows of the current store only through the tenant
 * scope): VEN-021b manual refund, VEN-022 / 022a returns with the transitions the store
 * may do (AfterSalesAccess), VEN-023 refunds (retry / complete / reject on refunds of
 * money the store collected).
 */
class VendorAfterSalesController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly RefundService $refunds,
        private readonly RefundPageService $refundPages,
        private readonly ReturnRequestService $returns,
        private readonly ReturnPageService $returnPages,
    ) {}

    public function refundForm(Request $request, string $order): View
    {
        $row = $this->order($order);

        return $this->remoteModal($request, 'after-sales::orders._refund-form', [
            'order' => $row,
            'remaining' => $this->refunds->remaining($this->tenantId(), $row),
            'action' => vendor_route('sales.orders.refund.store', ['order' => $row->uuid]),
            'canRestock' => $row->isOwnProducts(),
        ], new RemoteModalPage('vendor', __('after-sales::refunds.manual_title'), vendor_route('sales.orders.show', ['order' => $row->uuid]), null, null, __('after-sales::common.save'), [
            __('navigation.vendor.orders') => vendor_route('sales.orders.index'),
            $row->number => vendor_route('sales.orders.show', ['order' => $row->uuid]),
        ]));
    }

    public function refund(ManualRefundRequest $request, string $order): JsonResponse
    {
        $row = $this->order($order);
        $data = $request->toData();
        $refund = $this->refunds->createManual($this->tenantId(), $row, $row->isOwnProducts() ? $data : $data->with(['restock' => false]), $this->actor($request));

        return JsonResponder::created(__('after-sales::refunds.created', ['reference' => $refund->reference]), redirect: vendor_route('sales.orders.show', ['order' => $row->uuid]));
    }

    public function returns(Request $request): View
    {
        return view('after-sales::vendor.returns.index', [
            'tabs' => $this->returnPages->tabs(),
            'active' => (string) $request->query('tab', 'all'),
            'statuses' => ReturnStatus::options(),
            'types' => ReturnType::options(),
        ]);
    }

    public function returnsData(Request $request, ReturnsDataTable $table): JsonResponse
    {
        return $table->forSurface('vendor')->toJsonFromRequest($request, ['search', 'tab', 'status', 'type']);
    }

    public function showReturn(int $return): View
    {
        $row = $this->returns->find($return, $this->tenantId());
        $order = $this->returns->order($row);

        return view('after-sales::vendor.returns.show', $this->returnPages->detail($row, $order) + [
            'actions' => AfterSalesAccess::vendorActions($row, $order),
            'surface' => 'vendor',
        ]);
    }

    public function returnMedia(int $return, int $media): StreamedResponse
    {
        $file = $this->returns->media($this->returns->find($return, $this->tenantId()), $media);

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }

    public function returnActionForm(Request $request, int $return, string $action): View
    {
        $row = $this->returns->find($return, $this->tenantId());
        $order = $this->returns->order($row);
        $step = $this->allowedReturn($row, $order, $action);

        return $this->remoteModal($request, 'after-sales::returns._action-form', $this->returnPages->actionForm($row, $order, $step) + [
            'formAction' => vendor_route('sales.returns.actions.store', ['return' => $row->id, 'action' => $step->value]),
            'branches' => [],
        ], new RemoteModalPage('vendor', $step->label(), vendor_route('sales.returns.show', ['return' => $row->id]), null, null, $step->label(), [
            __('navigation.vendor.returns') => vendor_route('sales.returns.index'),
            $row->reference => null,
        ]));
    }

    public function returnAct(ReturnActionRequest $request, int $return, string $action): JsonResponse
    {
        $row = $this->returns->find($return, $this->tenantId());
        $this->allowedReturn($row, $this->returns->order($row), $action);
        $this->returns->act($row, $request->toData()->with(['branch_id' => null]), $this->actor($request));

        return JsonResponder::success(__('after-sales::returns.updated', ['reference' => $row->reference]), redirect: vendor_route('sales.returns.show', ['return' => $row->id]));
    }

    public function returnNote(ReturnNoteRequest $request, int $return): JsonResponse
    {
        $row = $this->returns->find($return, $this->tenantId());
        $this->returns->addNote($row, $request->note(), $request->customerVisible(), $this->actor($request));

        return JsonResponder::success(__('after-sales::returns.note_added'), redirect: vendor_route('sales.returns.show', ['return' => $row->id]));
    }

    public function refunds(Request $request): View
    {
        return view('after-sales::vendor.refunds.index', [
            'stats' => $this->refundPages->stats(),
            'active' => RefundStatus::tryFrom((string) $request->query('status'))->value ?? 'all',
            'sources' => RefundSource::options(),
        ]);
    }

    public function refundsData(Request $request, RefundsDataTable $table): JsonResponse
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $table->forSurface('vendor', $staff->can('refunds.manage'))->toJsonFromRequest($request, ['search', 'status', 'source', 'from', 'to']);
    }

    public function showRefund(Request $request, int $refund): View
    {
        $row = $this->findRefund($refund);
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $this->remoteModal($request, 'after-sales::refunds._show', [
            'refund' => $row->load('attemptLog', 'returnRequest'),
            'actions' => $staff->can('refunds.manage') && AfterSalesAccess::vendorCanActOnRefund($row) ? AfterSalesAccess::refundActions($row) : [],
            'surface' => 'vendor',
        ], new RemoteModalPage('vendor', $row->reference, vendor_route('sales.refunds.index'), null, null, null, [
            __('navigation.vendor.refunds') => vendor_route('sales.refunds.index'),
            $row->reference => null,
        ]));
    }

    public function refundActionForm(Request $request, int $refund, string $action): View
    {
        $row = $this->findRefund($refund);
        $this->allowedRefund($row, $action);

        return $this->remoteModal($request, 'after-sales::refunds._action-form', [
            'refund' => $row,
            'action' => $action,
            'formAction' => vendor_route('sales.refunds.action', ['refund' => $row->id]),
        ], new RemoteModalPage('vendor', $row->reference, vendor_route('sales.refunds.index'), null, null, __('after-sales::refunds.actions.'.$action), [
            __('navigation.vendor.refunds') => vendor_route('sales.refunds.index'),
            $row->reference => null,
        ]));
    }

    public function refundAct(RefundActionRequest $request, int $refund): JsonResponse
    {
        $row = $this->findRefund($refund);
        $this->allowedRefund($row, $request->action());
        $actor = $this->actor($request);

        match ($request->action()) {
            'retry' => $this->refunds->retry($row, $request->note(), $actor),
            'complete' => $this->refunds->markCompleted($row, $request->note(), $actor),
            default => $this->refunds->reject($row, (string) $request->note(), $actor),
        };

        return JsonResponder::success(__('after-sales::refunds.done.'.$request->action(), ['reference' => $row->reference]));
    }

    private function order(string $uuid): Order
    {
        $order = $this->refunds->order($this->tenantId(), $uuid);

        if (! AfterSalesAccess::vendorCanRefundOrder($order)) {
            throw AfterSalesException::rule('platform_collected', 403);
        }

        return $order;
    }

    private function findRefund(int $id): Refund
    {
        $refund = $this->refundPages->find($id);

        if ($refund->tenant_id !== $this->tenantId()) {
            throw AfterSalesException::rule('refund_not_found', 404);
        }

        return $refund;
    }

    private function allowedReturn(ReturnRequest $return, Order $order, string $action): ReturnAction
    {
        $step = ReturnAction::tryFrom($action) ?? abort(404);

        if (! in_array($step, AfterSalesAccess::vendorActions($return, $order), true)) {
            throw AfterSalesException::rule('transition_not_allowed', 422, ['action' => $step->label(), 'status' => $return->status->label()]);
        }

        return $step;
    }

    private function allowedRefund(Refund $refund, string $action): void
    {
        if (! AfterSalesAccess::vendorCanActOnRefund($refund) || ! in_array($action, AfterSalesAccess::refundActions($refund), true)) {
            throw AfterSalesException::rule('refund_action_not_allowed');
        }
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }

    private function actor(Request $request): Actor
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return new Actor(ActorType::Vendor, $staff->id, $staff->name);
    }
}
