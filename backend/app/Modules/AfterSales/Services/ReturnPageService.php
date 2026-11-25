<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\Enums\InspectionResult;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Models\ReturnRequestEvent;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\Orders\AdminOrderListService;

/**
 * View data of the returns pages (both panels): tabs with counts, the detail page and
 * the transition modals.
 */
class ReturnPageService
{
    public const TABS = ['open', 'pending', 'awaiting_info', 'approved', 'item_received', 'inspected', 'refunded', 'rejected', 'closed'];

    public function __construct(
        private readonly ReturnRequestRepositoryContract $returns,
        private readonly ReturnRequestService $service,
        private readonly ReturnPolicyService $policy,
        private readonly AdminOrderListService $lists,
    ) {}

    /**
     * @return array<string, array{label: string, count: int}>
     */
    public function tabs(): array
    {
        $counts = $this->returns->statusCounts();
        $tabs = ['all' => ['label' => (string) __('after-sales::returns.tabs.all'), 'count' => array_sum($counts)]];
        $tabs['open'] = ['label' => (string) __('after-sales::returns.tabs.open'), 'count' => array_sum(array_intersect_key($counts, array_flip(ReturnStatus::openValues())))];

        foreach ([ReturnStatus::Refunded, ReturnStatus::Rejected, ReturnStatus::Closed] as $status) {
            $tabs[$status->value] = ['label' => $status->label(), 'count' => $counts[$status->value] ?? 0];
        }

        return $tabs;
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(ReturnRequest $return, Order $order): array
    {
        $return->loadMissing(['events', 'media', 'refunds', 'tenant']);
        $reason = $this->policy->reasonByCode($return->reason_code);

        return [
            'return' => $return,
            'order' => $order,
            'item' => $order->items->firstWhere('id', $return->order_item_id),
            'reasonLabel' => $reason !== null ? $reason->name : $return->reason_code,
            'messages' => $return->events->filter(static fn (ReturnRequestEvent $event): bool => $event->customer_visible || $event->type === ReturnEventType::Message)->reverse()->values(),
            'timeline' => $return->events->filter(static fn (ReturnRequestEvent $event): bool => $event->type === ReturnEventType::Status),
            'notes' => $return->events->filter(static fn (ReturnRequestEvent $event): bool => $event->type === ReturnEventType::Note),
            'maxRefund' => $this->service->maxRefund($return, $order),
            'windowDays' => $this->policy->policy()->window_days,
            'storeName' => (string) $return->tenant?->getAttribute('name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actionForm(ReturnRequest $return, Order $order, ReturnAction $action): array
    {
        return [
            'return' => $return,
            'action' => $action,
            'maxRefund' => $this->service->maxRefund($return, $order),
            'results' => InspectionResult::options(),
            'branches' => $order->isOwnProducts() ? [] : $this->lists->branchOptions(),
            'branchId' => $order->fulfilment_branch_id,
            'canRefundNow' => $return->type->value === 'return',
        ];
    }
}
