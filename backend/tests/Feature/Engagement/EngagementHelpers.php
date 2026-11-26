<?php

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Str;

/*
| Shared helpers of the Engagement tests: an order of a customer with one line of a
| store product, in a given state (run inside the store's tenant context).
*/

if (! function_exists('engagementOrderLine')) {
    function engagementOrderLine(Customer $customer, StoreProduct $product, OrderStatus $status = OrderStatus::Delivered): OrderItem
    {
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'number' => 'T'.Str::upper(Str::random(10)),
            'checkout_group' => (string) Str::uuid(),
            'kind' => OrderKind::Own,
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'currency' => 'USD',
            'items_grand_total' => 1500,
            'grand_total' => 1500,
            'totals' => [],
            'placed_at' => now()->subDays(5),
            'delivered_at' => $status === OrderStatus::Delivered ? now()->subDay() : null,
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'line_key' => 'own:'.$product->id.':0',
            'store_product_id' => $product->id,
            'product_source' => StoreProductSource::Own,
            'product_id' => $product->id,
            'variant_key' => 0,
            'name' => (string) ($product->name ?: 'Product '.$product->id),
            'quantity' => 1,
            'cost' => 1000,
            'profit_amount' => 500,
            'price' => 1500,
            'sub_total' => 1500,
            'fixed_shipping' => 0,
            'discount' => 0,
            'tax' => 0,
            'shipping_fee' => 0,
            'grand_total' => 1500,
        ]);
    }
}
