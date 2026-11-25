<?php

namespace App\Modules\Commerce\Http\Resources\StoreApi;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Commerce\Support\MoneyPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer view of an order (history row; detail adds addresses, timeline). Profit split
 * and cost fields are internal and never exposed.
 *
 * @property Order $resource
 */
class OrderResource extends JsonResource
{
    public bool $detailed = false;

    public static function detail(Order $order): self
    {
        $resource = new self($order);
        $resource->detailed = true;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $money = app(MoneyPresenter::class);

        $data = [
            'uuid' => $order->uuid,
            'number' => $order->number,
            'kind' => $order->kind->value,
            'checkout_group' => $order->checkout_group,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'payment_status_label' => $order->payment_status->label(),
            'payment_gateway' => $order->payment_gateway,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'items_count' => (int) $order->items->sum('quantity'),
            'cancellable' => $order->status->isCancellableByCustomer(),
            'totals' => [
                'subtotal' => $money->present($order->display_subtotal),
                'flash_discount' => $money->present($order->flash_discount),
                'items_total' => $money->present($order->items_grand_total),
                'coupon_discount' => $money->present($order->coupon_discount),
                'shipping' => $money->present($order->shipping_charge),
                'tax' => $money->present($order->tax),
                'grand_total' => $money->present($order->grand_total),
            ],
            'coupon_code' => $order->coupon_code,
            'items' => $order->items->map(static fn (OrderItem $item): array => [
                'name' => $item->name,
                'variant' => $item->variant_label,
                'sku' => $item->sku,
                'image_url' => $item->image_url,
                'quantity' => $item->quantity,
                'unit_price' => $money->present($item->price),
                'subtotal' => $money->present($item->sub_total + $item->fixed_shipping),
                'discount' => $money->present($item->discount),
                'total' => $money->present($item->grand_total),
            ])->values()->all(),
            'display_currency' => $money->displayCurrency(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return $data + [
            'shipping_address' => $order->shipping_address,
            'billing_address' => $order->billing_address,
            'shipping' => [
                'company' => $order->shipping_company_name,
                'zone' => $order->shipping_quote['zone_name'] ?? null,
                'rate' => $order->shipping_quote['band_name'] ?? null,
                'tracking_number' => $order->tracking_number,
                'delivery_min_date' => $order->delivery_min_date?->toDateString(),
                'delivery_max_date' => $order->delivery_max_date?->toDateString(),
            ],
            'payment_expires_at' => $order->reservation_expires_at?->toIso8601String(),
            'timeline' => $order->history->map(static fn (OrderStatusHistory $row): array => [
                'status' => $row->to_status->value,
                'label' => $row->to_status->label(),
                'note' => $row->note,
                'at' => $row->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
