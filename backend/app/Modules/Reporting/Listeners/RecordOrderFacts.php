<?php

namespace App\Modules\Reporting\Listeners;

use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;

/**
 * Keeps the central order_facts row of a store order in sync (architecture: central
 * reporting facts fed by events): written on placement and on every status change.
 */
class RecordOrderFacts
{
    public function __construct(private readonly OrderFactRepositoryContract $facts) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        $this->facts->upsertFor($event->tenantId, $order->uuid, [
            'order_number' => $order->number,
            'kind' => $order->kind->value,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'customer_id' => $order->customer_id,
            'country_id' => $order->country_id,
            'currency' => $order->currency,
            'items_count' => (int) $order->items()->sum('quantity'),
            'items_grand_total' => $order->items_grand_total,
            'flash_discount' => $order->flash_discount,
            'shipping_charge' => $order->shipping_charge,
            'coupon_discount' => $order->coupon_discount,
            'coupon_source' => $order->coupon_source?->value,
            'grand_total' => $order->grand_total,
            'platform_profit' => $order->platform_profit,
            'vendor_profit' => $order->vendor_profit,
            'payment_gateway' => $order->payment_gateway,
            'placed_at' => $order->placed_at,
            'paid_at' => $order->paid_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'cancelled_at' => $order->cancelled_at,
        ]);
    }
}
