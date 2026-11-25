<?php

namespace App\Modules\AfterSales\Http\Controllers\Admin;

use App\Modules\AfterSales\DataTables\ReturnsDataTable;
use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Http\Requests\ReturnActionRequest;
use App\Modules\AfterSales\Http\Requests\ReturnNoteRequest;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\ReturnPageService;
use App\Modules\AfterSales\Services\ReturnRequestService;
use App\Modules\AfterSales\Support\AfterSalesAccess;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Orders\AdminOrderListService;
use App\Modules\Identity\Models\Admin;
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
 * Admin › Returns & refunds › Return requests (ADM-023 / 023a, Figma 70:36668 / 70:37613 /
 * 70:41386): status tabs, list of every store, detail with progress steps, order card,
 * timeline, customer messages, evidence / inspection photos and one modal per transition
 * (approve, reject, ask info, received, inspect + restock (+ refund), refund, ship /
 * complete exchange, close) plus notes.
 */
class ReturnController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly ReturnRequestService $returns,
        private readonly ReturnPageService $pages,
        private readonly AdminOrderListService $lists,
    ) {}

    public function index(Request $request): View
    {
        return view('after-sales::admin.returns.index', [
            'tabs' => $this->pages->tabs(),
            'active' => (string) $request->query('tab', 'all'),
            'statuses' => ReturnStatus::options(),
            'types' => ReturnType::options(),
        ]);
    }

    public function data(Request $request, ReturnsDataTable $table): JsonResponse
    {
        return $table->forSurface('admin')->toJsonFromRequest($request, ReturnsDataTable::FILTERS);
    }

    public function stores(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->lists->storeSearch($request->term(), $request->page()));
    }

    public function show(int $return): View
    {
        $row = $this->returns->find($return);
        $order = $this->returns->order($row);

        return view('after-sales::admin.returns.show', $this->pages->detail($row, $order) + [
            'actions' => AfterSalesAccess::adminActions($row, $order),
            'surface' => 'admin',
        ]);
    }

    public function media(int $return, int $media): StreamedResponse
    {
        $file = $this->returns->media($this->returns->find($return), $media);

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }

    public function actionForm(Request $request, int $return, string $action): View
    {
        $row = $this->returns->find($return);
        $order = $this->returns->order($row);
        $step = $this->allowed($row, $order, $action);

        return $this->remoteModal($request, 'after-sales::returns._action-form', $this->pages->actionForm($row, $order, $step) + [
            'formAction' => route('admin.returns.actions.store', [$row->id, $step->value]),
        ], new RemoteModalPage('admin', $step->label(), route('admin.returns.show', $row->id), null, null, $step->label(), [
            __('navigation.admin.returns_refunds') => null,
            __('navigation.admin.return_requests') => route('admin.returns.index'),
            $row->reference => route('admin.returns.show', $row->id),
        ]));
    }

    public function act(ReturnActionRequest $request, int $return, string $action): JsonResponse
    {
        $row = $this->returns->find($return);
        $this->allowed($row, $this->returns->order($row), $action);
        $this->returns->act($row, $request->toData(), $this->actor($request));

        return JsonResponder::success(__('after-sales::returns.updated', ['reference' => $row->reference]), redirect: route('admin.returns.show', $row->id));
    }

    public function note(ReturnNoteRequest $request, int $return): JsonResponse
    {
        $row = $this->returns->find($return);
        $this->returns->addNote($row, $request->note(), $request->customerVisible(), $this->actor($request));

        return JsonResponder::success(__('after-sales::returns.note_added'), redirect: route('admin.returns.show', $row->id));
    }

    private function allowed(ReturnRequest $return, Order $order, string $action): ReturnAction
    {
        $step = ReturnAction::tryFrom($action) ?? abort(404);

        if (! in_array($step, AfterSalesAccess::adminActions($return, $order), true)) {
            throw AfterSalesException::rule('transition_not_allowed', 422, ['action' => $step->label(), 'status' => $return->status->label()]);
        }

        return $step;
    }

    private function actor(Request $request): Actor
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return new Actor(ActorType::Admin, $admin->id, $admin->name);
    }
}
