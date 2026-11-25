<?php

namespace App\Modules\Reporting\Listeners;

use App\Modules\Commerce\Events\OrderFulfilmentUpdated;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;

/**
 * Keeps the central order_facts row of a store order in sync (architecture: central
 * reporting facts fed by events): written on placement, every status change and every shipping step.
 */
class RecordOrderFacts
{
    public function __construct(private readonly OrderFactRepositoryContract $facts) {}

    public function handle(OrderStatusChanged|OrderFulfilmentUpdated $event): void
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
            'order_id' => $order->id,
            'customer_name' => $order->customer_name,
            'customer_city' => self::city($order->shipping_address),
            'payment_source' => $order->payment_source?->value,
            'fulfilment_status' => $order->fulfilment_status->value,
            'shipping_company_id' => $order->shipping_company_id,
            'shipping_company_name' => $order->shipping_company_name,
            'tracking_number' => $order->tracking_number,
            'weight_grams' => (int) $order->items()->sum('weight_grams'),
            'fulfilment_branch_id' => $order->fulfilment_branch_id,
            'delivery_min_date' => $order->delivery_min_date,
            'delivery_max_date' => $order->delivery_max_date,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private static function city(?array $address): ?string
    {
        $city = $address['city_name'] ?? $address['city'] ?? null;

        return is_string($city) && $city !== '' ? mb_substr($city, 0, 120) : null;
    }
}
