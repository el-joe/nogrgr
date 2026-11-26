<?php

namespace App\Modules\Storefront\Services\Account;

use App\Modules\AfterSales\Services\CustomerReturnService;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Http\Resources\StoreApi\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
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

        return $data + [
            'order' => $order,
            'payment_label' => $this->paymentLabel($order),
            'delivered_at' => $order->delivered_at,
            'tracking' => $this->tracking($order),
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
