<?php

namespace App\Modules\AfterSales\Http\Controllers\Admin;

use App\Modules\AfterSales\DataTables\RefundsDataTable;
use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Http\Requests\RefundActionRequest;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Services\RefundPageService;
use App\Modules\AfterSales\Services\RefundService;
use App\Modules\AfterSales\Support\AfterSalesAccess;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Services\Orders\AdminOrderListService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Returns & refunds › Refunds (ADM-024, Figma 70:38484 / 70:41386): KPI cards,
 * status tabs, filters (store, status, source, dates), CSV export, refund detail with its
 * payment log, and the retry / mark completed / reject modal.
 */
class RefundController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly RefundService $refunds,
        private readonly RefundPageService $pages,
        private readonly AdminOrderListService $lists,
    ) {}

    public function index(Request $request): View
    {
        return view('after-sales::admin.refunds.index', [
            'stats' => $this->pages->stats(),
            'active' => RefundStatus::tryFrom((string) $request->query('status'))->value ?? 'all',
            'sources' => RefundSource::options(),
        ]);
    }

    public function data(Request $request, RefundsDataTable $table): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $table->forSurface('admin', $admin->can('refunds.manage'))->toJsonFromRequest($request, RefundsDataTable::FILTERS);
    }

    public function stores(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->lists->storeSearch($request->term(), $request->page()));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->pages->export($request->only(RefundsDataTable::FILTERS));
    }

    public function show(Request $request, int $refund): View
    {
        $row = $this->pages->find($refund);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $this->remoteModal($request, 'after-sales::refunds._show', [
            'refund' => $row->load('attemptLog', 'returnRequest', 'tenant'),
            'actions' => $admin->can('refunds.manage') && AfterSalesAccess::adminCanActOnRefund($row) ? AfterSalesAccess::refundActions($row) : [],
            'actionUrl' => route('admin.refunds.action', $row->id),
        ], new RemoteModalPage('admin', $row->reference, route('admin.refunds.index'), null, null, null, [
            __('navigation.admin.returns_refunds') => null,
            __('navigation.admin.refunds') => route('admin.refunds.index'),
            $row->reference => null,
        ]));
    }

    public function actionForm(Request $request, int $refund, string $action): View
    {
        $row = $this->pages->find($refund);
        $this->allowed($row, $action);

        return $this->remoteModal($request, 'after-sales::refunds._action-form', [
            'refund' => $row,
            'action' => $action,
            'formAction' => route('admin.refunds.action', $row->id),
        ], new RemoteModalPage('admin', $row->reference, route('admin.refunds.index'), null, null, __('after-sales::refunds.actions.'.$action), [
            __('navigation.admin.refunds') => route('admin.refunds.index'),
            $row->reference => null,
        ]));
    }

    public function act(RefundActionRequest $request, int $refund): JsonResponse
    {
        $row = $this->pages->find($refund);
        $this->allowed($row, $request->action());
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $actor = new Actor(ActorType::Admin, $admin->id, $admin->name);

        match ($request->action()) {
            'retry' => $this->refunds->retry($row, $request->note(), $actor),
            'complete' => $this->refunds->markCompleted($row, $request->note(), $actor),
            default => $this->refunds->reject($row, (string) $request->note(), $actor),
        };

        return JsonResponder::success(__('after-sales::refunds.done.'.$request->action(), ['reference' => $row->reference]));
    }

    private function allowed(Refund $refund, string $action): void
    {
        if (! AfterSalesAccess::adminCanActOnRefund($refund) || ! in_array($action, AfterSalesAccess::refundActions($refund), true)) {
            throw AfterSalesException::rule('refund_action_not_allowed');
        }
    }
}
