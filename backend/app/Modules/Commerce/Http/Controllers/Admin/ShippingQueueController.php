<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Modules\Commerce\DataTables\ShippingQueueDataTable;
use App\Modules\Commerce\DTOs\Orders\FulfilmentUpdateData;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\AttachmentKind;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Http\Requests\Orders\AttachmentRequest;
use App\Modules\Commerce\Http\Requests\Orders\BulkShipRequest;
use App\Modules\Commerce\Http\Requests\Orders\FulfilmentStatusRequest;
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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Sales › Shipping queue (ADM-022 / 022a, Figma 70:34839 / 70:35864): tabs to
 * ship / in transit / delayed / delivered today, bulk "mark shipped", shipment detail
 * with tracking timeline, status modal (carrier, AWB, location, branch, notify),
 * attachments on the private central disk, shipping label print and "create return".
 */
class ShippingQueueController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly AdminOrderService $orders,
        private readonly AdminOrderListService $lists,
        private readonly ShippingCompanyService $companies,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'to_ship');

        return view('commerce::admin.shipping.index', [
            'tabs' => $this->lists->queueTabs(),
            'active' => in_array($tab, ['to_ship', 'in_transit', 'delayed', 'delivered'], true) ? $tab : 'to_ship',
            'companies' => $this->companies->activeOptions(),
            'branches' => $this->lists->branchOptions(),
            'countries' => $this->lists->countryOptions(),
        ]);
    }

    public function data(Request $request, ShippingQueueDataTable $table): JsonResponse
    {
        return $table->withBranches($this->lists->branchOptions())->toJsonFromRequest($request, ShippingQueueDataTable::FILTERS);
    }

    public function show(int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);
        $this->guard($context->order->isOwnProducts());

        return view('commerce::admin.shipping.show', [
            'fact' => $row,
            'context' => $context,
            'order' => $context->order,
            'branches' => $this->lists->branchOptions(),
            'kinds' => AttachmentKind::options(),
        ] + $this->orders->detail($context));
    }

    public function label(int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);
        $this->guard($context->order->isOwnProducts());

        return view('commerce::admin.shipping.label', ['fact' => $row, 'context' => $context, 'order' => $context->order, 'branches' => $this->lists->branchOptions()]);
    }

    public function statusForm(Request $request, int $fact): View
    {
        $row = $this->orders->fact($fact);
        $context = $this->orders->context($row);
        $this->guard($context->order->isOwnProducts());
        $current = $context->order->fulfilment_status;

        return $this->remoteModal($request, 'commerce::admin.shipping._status-form', [
            'fact' => $row,
            'order' => $context->order,
            'statuses' => array_intersect_key(FulfilmentStatus::options(), array_flip(array_map(static fn (FulfilmentStatus $s): string => $s->value, $current->transitions()))),
            'companies' => $this->companies->activeOptions(),
            'branches' => $this->lists->branchOptions(),
            'action' => route('admin.shipping-queue.status.update', $row->id),
        ], new RemoteModalPage(
            surface: 'admin',
            title: __('commerce::fulfilment.status_modal.title', ['number' => $row->order_number]),
            backUrl: route('admin.shipping-queue.show', $row->id),
            formId: null,
            submitLabel: __('commerce::orders_admin.save'),
            breadcrumbs: [__('navigation.sections.sales') => null, __('navigation.admin.shipping_queue') => route('admin.shipping-queue.index'), $row->order_number => null],
        ));
    }

    public function updateStatus(FulfilmentStatusRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $this->orders->updateFulfilment($this->orders->context($row), $request->toData(ActorType::Admin, $this->admin($request)->id));

        return JsonResponder::success(__('commerce::fulfilment.updated', ['number' => $row->order_number]), redirect: $request->boolean('stay') ? null : route('admin.shipping-queue.show', $row->id));
    }

    public function bulkShip(BulkShipRequest $request): JsonResponse
    {
        $done = 0;
        $skipped = 0;

        foreach ($request->ids() as $id) {
            try {
                $context = $this->orders->context($this->orders->fact($id));

                if ($context->order->fulfilment_status === FulfilmentStatus::Pending) {
                    $this->orders->updateFulfilment($context, new FulfilmentUpdateData(FulfilmentStatus::Packed, ActorType::Admin, $this->admin($request)->id));
                }

                $this->orders->updateFulfilment($context, new FulfilmentUpdateData(FulfilmentStatus::InTransit, ActorType::Admin, $this->admin($request)->id));
                $done++;
            } catch (CommerceException) {
                $skipped++;
            }
        }

        return JsonResponder::success(__('commerce::fulfilment.queue.bulk_done', ['done' => $done, 'skipped' => $skipped]), ['done' => $done, 'skipped' => $skipped]);
    }

    public function upload(AttachmentRequest $request, int $fact): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $this->orders->attach($this->orders->context($row), $request->upload(), $request->kind(), $this->admin($request));

        return JsonResponder::created(__('commerce::fulfilment.attachments.uploaded'), redirect: route('admin.shipping-queue.show', $row->id));
    }

    public function download(int $fact, int $attachment): StreamedResponse
    {
        $file = $this->orders->attachment($this->orders->fact($fact), $attachment);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroyAttachment(int $fact, int $attachment): JsonResponse
    {
        $row = $this->orders->fact($fact);
        $this->orders->deleteAttachment($row, $attachment);

        return JsonResponder::success(__('commerce::fulfilment.attachments.deleted'), redirect: route('admin.shipping-queue.show', $row->id));
    }

    private function guard(bool $own): void
    {
        if ($own) {
            throw CommerceException::rule('own_products_read_only', 403);
        }
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }
}
