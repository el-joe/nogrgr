<?php

namespace App\Modules\Storefront\Services\Account;

use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Modules\AfterSales\Services\CustomerReturnService;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Http\Resources\StoreApi\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Commerce\Models\OrderTrackingEvent;
use App\Modules\Commerce\Services\Orders\CustomerOrderService;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Engagement\Repositories\Eloquent\ProductReviewRepository;
use App\Modules\Engagement\Services\Reviews\ReviewService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * View data of the storefront account order pages (SF-ECO-024/027/028/029): the Store API
 * customer order shape (OrderResource) plus what the theme pages show around it — line
 * ids for reviews / reorder, review and return eligibility, the tracking timeline and the
 * carrier link. Orders, cancellation, reviews and returns stay in their own modules.
 */
class AccountOrderPages
{
    /**
     * Status chips of "My orders" → order statuses.
     *
     * @var array<string, list<string>|null>
     */
    public const TABS = [
        'all' => null,
        'pending' => ['pending_payment'],
        'processing' => ['paid', 'processing'],
        'shipped' => ['shipped'],
        'delivered' => ['delivered', 'completed'],
        'cancelled' => ['cancelled'],
        'refunded' => ['refunded', 'returned'],
    ];

    public function __construct(
        private readonly CustomerOrderService $orders,
        private readonly ReviewService $reviews,
        private readonly CustomerReturnService $returns,
        private readonly RefundRepositoryContract $refunds,
        private readonly ShippingCompanyRepositoryContract $companies,
        private readonly MoneyPresenter $money,
        private readonly StorefrontContext $context,
    ) {}

    public static function tab(?string $tab): string
    {
        return is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'all';
    }

    /**
     * @return array{page: LengthAwarePaginator<int, Order>, rows: list<array<string, mixed>>}
     */
    public function history(Customer $customer, Request $request, string $tab, ?string $search, int $perPage = 10): array
    {
        $this->useDisplayCurrency();
        $page = $this->orders->history($customer, ['status' => self::TABS[$tab], 'search' => $search !== null && trim($search) !== '' ? trim($search) : null], $perPage);
        $page->appends(array_filter(['status' => $tab === 'all' ? null : $tab, 'q' => $search]));
        $orders = collect($page->items());
        $reviewed = $this->reviews->reviewedLines($orders->flatMap(static fn (Order $order) => $order->items->pluck('id'))->map(static fn ($id): int => (int) $id)->values()->all());

        return [
            'page' => $page,
            'rows' => $orders->map(fn (Order $order): array => $this->row($order, $request, $reviewed))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Customer $customer, Request $request, string $uuid): array
    {
        $this->useDisplayCurrency();
        $order = $this->orders->find($customer, $uuid)->loadMissing('trackingEvents');
        $reviewed = $this->reviews->reviewedLines($order->items->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        $data = $this->row($order, $request, $reviewed, true);
        $options = $this->returns->options((int) tenant()?->getTenantKey(), $order);
        $refunds = $this->refundRows((int) tenant()?->getTenantKey(), $order);
        $refunded = (int) $this->refunds->sumForOrder((int) tenant()?->getTenantKey(), $order->uuid, [RefundStatus::Completed->value]);

        if ($refunded > 0 && $refunded < $order->grand_total) {
            $data['payment_status_label'] = __st('orders.partially_refunded');
        }

        return $data + [
            'order' => $order,
            'cancellation' => $this->cancellation($order),
            'refunds' => $refunds,
            'item_returns' => $this->itemReturns($customer, $order, $options['lines']),
            'activity' => $order->history->sortBy('id')->values()->map(static fn (OrderStatusHistory $row): array => ['label' => $row->to_status->label(), 'at' => $row->created_at])->all(),
            'payment_label' => $this->paymentLabel($order),
            'delivered_at' => $order->delivered_at,
            'tracking' => [...$this->upcoming($order), ...$this->tracking($order)],
            'carrier' => $this->carrier($order),
            'returns_left' => (int) array_sum(array_map(static fn (array $line): int => $line['returnable'] ? $line['available'] : 0, $options['lines'])),
            'can_return' => $options['block'] === null,
            'same_billing' => $order->billing_address === null || $order->billing_address === $order->shipping_address,
        ];
    }

    /**
     * @param  list<int>  $reviewed
     * @return array<string, mixed>
     */
    private function row(Order $order, Request $request, array $reviewed, bool $detailed = false): array
    {
        $data = ($detailed ? OrderResource::detail($order) : new OrderResource($order))->toArray($request);
        $delivered = in_array($order->status, ProductReviewRepository::DELIVERED_STATUSES, true);
        $lines = $order->items->values();

        $data['items'] = array_map(static function (array $item, int $index) use ($lines, $delivered, $reviewed): array {
            /** @var OrderItem $line */
            $line = $lines[$index];

            return $item + [
                'id' => $line->id,
                'store_product_id' => $line->store_product_id,
                'variant_id' => $line->variant_key,
                'reviewable' => $delivered && ! in_array($line->id, $reviewed, true),
                'reviewed' => in_array($line->id, $reviewed, true),
            ];
        }, $data['items'], array_keys($data['items']));

        $data['placed'] = $order->placed_at ?? $order->created_at;
        $data['reviewable'] = collect($data['items'])->contains('reviewable', true);
        $data['trackable'] = in_array($order->status, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed], true) || ($order->tracking_number !== null && $order->tracking_number !== '');
        $data['tone'] = self::tone($order->status);

        return $data;
    }

    /**
     * A presented amount (MoneyPresenter::present) in the display currency.
     *
     * @param  array{formatted: string, display: array{formatted: string}|null}|null  $amount
     */
    public static function money(?array $amount): string
    {
        return $amount === null ? '' : ($amount['display']['formatted'] ?? $amount['formatted']);
    }

    public static function tone(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Delivered, OrderStatus::Completed => 'success',
            OrderStatus::Shipped => 'info',
            OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing => 'warning',
            OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Returned => 'danger',
        };
    }

    /**
     * Why, when and by whom a cancelled order was cancelled (staff notes stay internal).
     *
     * @return array{reason: string|null, at: Carbon|null, by: string, refund: string|null}|null
     */
    private function cancellation(Order $order): ?array
    {
        $row = $order->history->sortByDesc('id')->first(static fn (OrderStatusHistory $entry): bool => $entry->to_status === OrderStatus::Cancelled);

        if ($order->cancelled_at === null && $row === null) {
            return null;
        }

        $actor = $row->actor_type ?? ActorType::System;

        return [
            'reason' => $order->cancel_reason_code?->label() ?? ($actor === ActorType::Customer || $actor === ActorType::System ? $order->cancel_reason : null),
            'at' => $order->cancelled_at ?? $row?->created_at,
            'by' => match ($actor) {
                ActorType::Customer => 'you',
                ActorType::System, ActorType::Gateway => 'system',
                default => 'store',
            },
            'refund' => match (true) {
                $order->payment_status === PaymentStatus::Refunded => 'done',
                $order->paid_at !== null => 'pending',
                default => null,
            },
        ];
    }

    /**
     * The order's refunds, newest first (gateway ids, failures and approvers stay internal).
     *
     * @return list<array<string, mixed>>
     */
    private function refundRows(int $tenantId, Order $order): array
    {
        return $this->refunds->forOrder($tenantId, $order->uuid)->sortByDesc('id')->values()->map(fn (Refund $refund): array => [
            'reference' => $refund->reference,
            'amount' => $this->money->present($refund->amount),
            'status' => $refund->status->label(),
            'tone' => match ($refund->status) {
                RefundStatus::Completed => 'success',
                RefundStatus::Rejected => 'danger',
                default => 'warning',
            },
            'method' => $refund->method->label(),
            'requested_at' => $refund->requested_at,
            'processed_at' => $refund->status === RefundStatus::Completed ? $refund->processed_at : null,
            'rejection_reason' => $refund->status === RefundStatus::Rejected ? $refund->notes : null,
        ])->all();
    }

    /**
     * Per order line: the latest return request and whether another one can be opened.
     *
     * @param  list<array{item: OrderItem, available: int, returnable: bool}>  $lines
     * @return array<int, array{request: array{id: int, label: string, tone: string}|null, available: int, returnable: bool}>
     */
    private function itemReturns(Customer $customer, Order $order, array $lines): array
    {
        $requests = collect($this->returns->list((int) tenant()?->getTenantKey(), $customer, $order->uuid, 100)->items())->sortByDesc('id');
        $rows = [];

        foreach ($lines as $line) {
            /** @var ReturnRequest|null $latest */
            $latest = $requests->firstWhere('order_item_id', $line['item']->id);
            $rows[$line['item']->id] = [
                'request' => $latest === null ? null : ['id' => $latest->id, 'label' => $latest->type->label().': '.$latest->status->label(), 'tone' => AccountReturnPages::tone($latest->status->value)],
                'available' => $line['available'],
                'returnable' => $line['returnable'],
            ];
        }

        return $rows;
    }

    /**
     * Steps an open order has not reached yet (old tracking timeline), newest first so
     * they sit above what already happened. Closed orders have none.
     *
     * @return list<array{label: string, note: string|null, at: Carbon|null, done: bool, upcoming: bool}>
     */
    private function upcoming(Order $order): array
    {
        if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped], true)) {
            return [];
        }

        $outForDelivery = $order->trackingEvents->contains(static fn (OrderTrackingEvent $event): bool => $event->status === FulfilmentStatus::OutForDelivery);
        $reached = match (true) {
            $order->status === OrderStatus::PendingPayment => 0,
            $order->status === OrderStatus::Paid => 1,
            $order->status === OrderStatus::Processing => 2,
            $outForDelivery => 4,
            default => 3,
        };
        $steps = [1 => 'confirmed', 2 => 'processing', 3 => 'shipped', 4 => 'out_for_delivery', 5 => 'delivered'];

        return collect($steps)->filter(static fn (string $step, int $index): bool => $index > $reached)
            ->reverse()
            ->map(static fn (string $step): array => ['label' => __st('orders.timeline.'.$step), 'note' => null, 'at' => null, 'done' => false, 'upcoming' => true])
            ->values()->all();
    }

    /**
     * Newest first: shipping progress steps, then the "order placed" step.
     *
     * @return list<array{label: string, note: string|null, at: Carbon|null, done: bool}>
     */
    private function tracking(Order $order): array
    {
        $steps = $order->trackingEvents->sortByDesc('id')->values()->map(static fn (OrderTrackingEvent $event): array => [
            'label' => $event->status->label(),
            'note' => $event->location,
            'at' => $event->created_at,
            'done' => $event->status->value === 'delivered',
        ])->all();

        if ($steps === []) {
            foreach ($order->history->sortByDesc('id') as $row) {
                if ($row->to_status !== OrderStatus::PendingPayment) {
                    $steps[] = ['label' => $row->to_status->label(), 'note' => null, 'at' => $row->created_at, 'done' => in_array($row->to_status, ProductReviewRepository::DELIVERED_STATUSES, true)];
                }
            }
        }

        $steps[] = ['label' => '__placed', 'note' => null, 'at' => $order->placed_at ?? $order->created_at, 'done' => false];

        return array_values($steps);
    }

    /**
     * @return array{name: string|null, tracking: string|null, url: string|null}
     */
    private function carrier(Order $order): array
    {
        $url = null;
        $company = $order->shipping_company_id !== null ? $this->companies->find($order->shipping_company_id) : null;

        if ($company instanceof ShippingCompany && $order->tracking_number && $company->tracking_url_pattern) {
            $url = str_replace(ShippingCompany::TRACKING_PLACEHOLDER, rawurlencode($order->tracking_number), $company->tracking_url_pattern);
        }

        return ['name' => $order->shipping_company_name, 'tracking' => $order->tracking_number, 'url' => $url];
    }

    private function paymentLabel(Order $order): ?string
    {
        if ($order->payment_gateway === null || $order->payment_gateway === '') {
            return null;
        }

        return PaymentGatewayCode::tryFrom($order->payment_gateway)?->brand() ?? Str::headline($order->payment_gateway);
    }

    private function useDisplayCurrency(): void
    {
        $this->money->useDisplayCurrency($this->context->currencyCode());
    }
}
