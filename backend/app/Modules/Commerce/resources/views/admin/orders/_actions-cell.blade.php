<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.orders.show', $fact->id)" :label="__('commerce::orders_admin.view', ['number' => $fact->order_number])" />
    <x-ui::icon-button icon="printer" size="sm" :href="route('admin.orders.receipt', $fact->id)" target="_blank" :label="__('commerce::orders_admin.receipt')" />
    @if (! $fact->isOwnProducts() && auth('admin')->user()?->can('shipping-queue.view') && in_array($fact->status, ['paid', 'processing', 'shipped'], true))
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="truck" :href="route('admin.shipping-queue.show', $fact->id)">{{ __('commerce::orders_admin.open_shipment') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
