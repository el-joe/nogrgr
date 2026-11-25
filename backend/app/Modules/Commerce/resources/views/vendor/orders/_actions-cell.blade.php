<x-ui::row-actions>
    <x-ui::dropdown-item icon="eye" :href="vendor_route('sales.orders.show', ['order' => $order->uuid])">{{ __('commerce::vendor_orders.view') }}</x-ui::dropdown-item>
    <x-ui::dropdown-item icon="printer" :href="vendor_route('sales.orders.invoice', ['order' => $order->uuid])">{{ __('commerce::vendor_orders.print_invoice') }}</x-ui::dropdown-item>
</x-ui::row-actions>
