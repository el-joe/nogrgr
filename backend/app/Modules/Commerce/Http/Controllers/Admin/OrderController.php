<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Modules\Commerce\DataTables\AdminOrdersDataTable;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\CancellationReason;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Http\Requests\Orders\AdminNoteRequest;
use App\Modules\Commerce\Http\Requests\Orders\CancelOrderRequest;
use App\Modules\Commerce\Http\Requests\Orders\ReassignCompanyRequest;
use App\Modules\Commerce\Services\Orders\AdminOrderListService;
use App\Modules\Commerce\Services\Orders\AdminOrderService;
use App\Modules\Identity\Models\Admin;
use App\Modules\Shipping\Services\ShippingCompanyService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Sales › Orders (ADM-020 list from order_facts, ADM-021 detail read from the
 * store database, Figma 48:16236 / 49:17280 / 70:41386): status tabs + filters + CSV
 * export, detail with progress, timeline, tracking, settlement split, internal notes,
 * receipt print; cancel (2-step, reason codes) and reassign carrier modals. Refund and
 * create-return modals live in AfterSales. Own-products orders are read-only.
 */
class OrderController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly AdminOrderService $orders,
        private readonly AdminOrderListService $lists,
        private readonly ShippingCompanyService $companies,
    ) {}

    public function index(Request $request): View
    {
        return view('commerce::admin.orders.index', [
            'tabs' => $this->lists->statusTabs(),
            'active' => OrderStatus::tryFrom((string) $request->query('status'))->value ?? 'all',
            'payments' => PaymentStatus::options(),
            'gateways' => $this->lists->gatewayOptions(),
            'countries' => $this->lists->countryOptions(),
        ]);
    }

    public function data(Request $request, AdminOrdersDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, AdminOrdersDataTable::FILTERS);
    }

    public function stores(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->lists->storeSearch($request->term(), $request->page()));
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->lists->export($request->only(AdminOrdersDataTable::FILTERS));
    }

    public function show(int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);

        return view('commerce::admin.orders.show', [
            'fact' => $row,
            'context' => $context,
            'order' => $context->order,
        ] + $this->orders->detail($context));
    }

    public function receipt(int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);

        return view('commerce::admin.orders.receipt', ['fact' => $row, 'context' => $context, 'order' => $context->order]);
    }

    public function cancelForm(Request $request, int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);
        $detail = $this->orders->detail($context);

        return $this->remoteModal($request, 'commerce::admin.orders._cancel-form', [
            'fact' => $row,
            'order' => $context->order,
            'preview' => $detail['cancelPreview'],
            'reasons' => CancellationReason::staffOptions(),
            'action' => route('admin.orders.cancel', $row->id),
        ], $this->page(__('commerce::orders_admin.cancel.title', ['number' => $row->order_number]), $row->id, ''));
    }

    public function cancel(CancelOrderRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $this->orders->cancel($this->orders->context($row), $request->toData(ActorType::Admin, $this->admin($request)->id));

        return JsonResponder::success(__('commerce::orders_admin.cancel.done', ['number' => $row->order_number]), redirect: route('admin.orders.show', $row->id));
    }

    public function companyForm(Request $request, int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);

        return $this->remoteModal($request, 'commerce::admin.orders._company-form', [
            'fact' => $row,
            'order' => $context->order,
            'companies' => $this->companies->activeOptions(),
            'action' => route('admin.orders.company.update', $row->id),
            'estimateUrl' => route('admin.orders.company.estimate', $row->id),
        ], $this->page(__('commerce::fulfilment.reassign.title'), $row->id, ''));
    }

    public function estimate(ReassignCompanyRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $estimate = $this->orders->previewCompany($this->orders->context($row), $request->companyId());

        return JsonResponder::success(data: ['text' => $estimate !== null ? __('commerce::fulfilment.reassign.estimate', [
            'from' => $estimate->minDate->translatedFormat('j M'),
            'to' => $estimate->maxDate->translatedFormat('j M'),
            'company' => (string) $estimate->companyName,
            'skipped' => count($estimate->skipped),
        ]) : __('commerce::fulfilment.reassign.no_estimate')]);
    }

    public function reassign(ReassignCompanyRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $this->orders->reassignCompany($this->orders->context($row), $request->companyId(), $this->admin($request));

        return JsonResponder::success(__('commerce::fulfilment.reassign.done'), redirect: route('admin.orders.show', $row->id));
    }

    public function note(AdminNoteRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $this->orders->addNote($this->orders->context($row), $request->note(), $this->admin($request));

        return JsonResponder::success(__('commerce::orders_admin.notes.added'), redirect: route('admin.orders.show', $row->id));
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }

    private function page(string $title, int $fact, string $formId): RemoteModalPage
    {
        return new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: route('admin.orders.show', $fact),
            formId: null,
            submitLabel: __('commerce::orders_admin.save'),
            breadcrumbs: [__('navigation.sections.sales') => null, __('navigation.admin.orders') => route('admin.orders.index'), $title => null],
        );
    }
}
