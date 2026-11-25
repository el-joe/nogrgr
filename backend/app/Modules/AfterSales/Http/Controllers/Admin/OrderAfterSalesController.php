<?php

namespace App\Modules\AfterSales\Http\Controllers\Admin;

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Http\Requests\CreateReturnRequest;
use App\Modules\AfterSales\Http\Requests\ManualRefundRequest;
use App\Modules\AfterSales\Services\RefundService;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\AfterSales\Services\ReturnRequestService;
use App\Modules\AfterSales\Support\AfterSalesAccess;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Services\Orders\AdminOrderService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Order detail / shipment detail modals of the admin that belong to after-sales:
 * ADM-021b refund (amount ≤ refundable) and ADM-021c / 022a create return (reason ≥ 10
 * characters). Own-products orders are read-only for the platform.
 */
class OrderAfterSalesController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly AdminOrderService $orders,
        private readonly RefundService $refunds,
        private readonly ReturnRequestService $returns,
        private readonly ReturnPolicyService $policy,
    ) {}

    public function refundForm(Request $request, int $fact): View
    {
        $row = $this->orders->fact($fact);
        $order = $this->orders->context($row)->order;
        $this->guard(AfterSalesAccess::adminCanCreate($order));

        return $this->remoteModal($request, 'after-sales::orders._refund-form', [
            'order' => $order,
            'remaining' => $this->refunds->remaining($row->tenant_id, $order),
            'action' => route('admin.orders.refunds.store', $row->id),
            'canRestock' => true,
        ], $this->page(__('after-sales::refunds.issue_title'), $row->id, ''));
    }

    public function refund(ManualRefundRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $order = $this->orders->context($row)->order;
        $this->guard(AfterSalesAccess::adminCanCreate($order));
        $refund = $this->refunds->createManual($row->tenant_id, $order, $request->toData(), $this->actor($request));

        return JsonResponder::created(__('after-sales::refunds.created', ['reference' => $refund->reference]), redirect: route('admin.orders.show', $row->id));
    }

    public function returnForm(Request $request, int $fact): View
    {
        $row = $this->orders->fact($fact);
        $order = $this->orders->context($row)->order;
        $this->guard(AfterSalesAccess::adminCanCreate($order) && $this->returns->canOpenFor($order));

        return $this->remoteModal($request, 'after-sales::orders._return-form', [
            'order' => $order,
            'lines' => $this->returns->returnableLines($row->tenant_id, $order),
            'reasons' => $this->policy->reasonOptions(),
            'types' => $this->policy->policy()->allow_exchanges ? ReturnType::options() : [ReturnType::Return->value => ReturnType::Return->label()],
            'action' => route('admin.orders.returns.store', $row->id),
        ], $this->page(__('after-sales::returns.create_title'), $row->id, ''));
    }

    public function storeReturn(CreateReturnRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $order = $this->orders->context($row)->order;
        $this->guard(AfterSalesAccess::adminCanCreate($order));
        $return = $this->returns->create($row->tenant_id, $order, $request->toData(), $this->actor($request));

        return JsonResponder::created(__('after-sales::returns.created', ['reference' => $return->reference]), redirect: route('admin.returns.show', $return->id));
    }

    private function guard(bool $allowed): void
    {
        if (! $allowed) {
            throw AfterSalesException::rule('order_read_only', 403);
        }
    }

    private function actor(Request $request): Actor
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return new Actor(ActorType::Admin, $admin->id, $admin->name);
    }

    private function page(string $title, int $fact, string $formId): RemoteModalPage
    {
        return new RemoteModalPage('admin', $title, route('admin.orders.show', $fact), null, null, __('after-sales::common.save'), [
            __('navigation.sections.sales') => null,
            __('navigation.admin.orders') => route('admin.orders.index'),
            $title => null,
        ]);
    }
}
