<?php

namespace App\Modules\Commerce\Http\Controllers\Vendor;

use App\Modules\Commerce\DataTables\VendorOrdersDataTable;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\CancellationReason;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Http\Requests\Orders\CancelOrderRequest;
use App\Modules\Commerce\Http\Requests\Orders\FulfilmentStatusRequest;
use App\Modules\Commerce\Services\Orders\AdminOrderListService;
use App\Modules\Commerce\Services\Orders\VendorOrderService;
use App\Modules\Identity\Models\VendorStaff;
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
 * Vendor › Orders (VEN-020 / 021, Figma 12:15191 / 84:102416 / 84:107236): KPI cards +
 * order queue (filters, CSV report), detail with totals, settlement figures, print
 * invoice; cancel (reason codes), manual refund (AfterSales) and — own-products orders
 * only — the shipping status modal; "Pay platform" opens VEN-021d.
 */
class OrderController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly VendorOrderService $orders,
        private readonly AdminOrderListService $lists,
        private readonly ShippingCompanyService $companies,
    ) {}

    public function index(): View
    {
        return view('commerce::vendor.orders.index', [
            'stats' => $this->orders->stats(),
            'statuses' => OrderStatus::options(),
            'payments' => PaymentStatus::options(),
            'gateways' => $this->lists->gatewayOptions(),
            'kinds' => OrderKind::options(),
        ]);
    }

    public function data(Request $request, VendorOrdersDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, VendorOrdersDataTable::FILTERS);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->orders->export($request->only(VendorOrdersDataTable::FILTERS));
    }

    public function show(string $order): View
    {
        $row = $this->orders->find($order);

        return view('commerce::vendor.orders.show', ['order' => $row] + $this->orders->detail($row));
    }

    public function invoice(string $order): View
    {
        $row = $this->orders->find($order);

        return view('commerce::vendor.orders.invoice', ['order' => $row, 'store' => tenant('name')]);
    }

    public function cancelForm(Request $request, string $order): View
    {
        $row = $this->orders->find($order);

        return $this->remoteModal($request, 'commerce::admin.orders._cancel-form', [
            'order' => $row,
            'preview' => $this->orders->detail($row)['cancelPreview'],
            'reasons' => CancellationReason::staffOptions(),
            'action' => vendor_route('sales.orders.cancel', ['order' => $row->uuid]),
            'vendor' => true,
        ], $this->page(__('commerce::orders_admin.cancel.title', ['number' => $row->number]), $row->uuid, ''));
    }

    public function cancel(CancelOrderRequest $request, string $order): JsonResponse
    {
        $row = $this->orders->find($order);
        $this->orders->cancel($row, $request->toData(ActorType::Vendor, $this->staff($request)->id));

        return JsonResponder::success(__('commerce::orders_admin.cancel.done', ['number' => $row->number]), redirect: vendor_route('sales.orders.show', ['order' => $row->uuid]));
    }

    public function shippingForm(Request $request, string $order): View
    {
        $row = $this->orders->find($order);

        return $this->remoteModal($request, 'commerce::admin.shipping._status-form', [
            'order' => $row,
            'statuses' => array_intersect_key(FulfilmentStatus::options(), array_flip(array_map(static fn (FulfilmentStatus $s): string => $s->value, $row->fulfilment_status->transitions()))),
            'companies' => $this->companies->activeOptions(),
            'branches' => [],
            'action' => vendor_route('sales.orders.shipping.update', ['order' => $row->uuid]),
            'vendor' => true,
        ], $this->page(__('commerce::fulfilment.status_modal.vendor_title'), $row->uuid, ''));
    }

    public function updateShipping(FulfilmentStatusRequest $request, string $order): JsonResponse
    {
        $row = $this->orders->find($order);
        $this->orders->updateShipping($row, $request->toData(ActorType::Vendor, $this->staff($request)->id));

        return JsonResponder::success(__('commerce::fulfilment.updated', ['number' => $row->number]), redirect: vendor_route('sales.orders.show', ['order' => $row->uuid]));
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }

    private function page(string $title, string $uuid, string $formId): RemoteModalPage
    {
        return new RemoteModalPage('vendor', $title, vendor_route('sales.orders.show', ['order' => $uuid]), null, null, __('commerce::orders_admin.save'), [
            __('navigation.vendor.orders') => vendor_route('sales.orders.index'),
            $title => null,
        ]);
    }
}
