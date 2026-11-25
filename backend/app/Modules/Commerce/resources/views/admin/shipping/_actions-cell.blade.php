<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.shipping-queue.show', $fact->id)" :label="__('commerce::fulfilment.queue.view', ['number' => $fact->order_number])" />
    @if (auth('admin')->user()?->can('shipping-queue.manage') && $fact->fulfilment_status !== 'delivered')
        <x-ui::icon-button icon="edit" size="sm" data-remote-modal="shipping-modal" :data-url="route('admin.shipping-queue.status.form', $fact->id)" :label="__('commerce::fulfilment.update_status')" />
    @endif
    <x-ui::row-actions>
        <x-ui::dropdown-item icon="printer" :href="route('admin.shipping-queue.label', $fact->id)" target="_blank">{{ __('commerce::fulfilment.print_label') }}</x-ui::dropdown-item>
        <x-ui::dropdown-item icon="cart" :href="route('admin.orders.show', $fact->id)">{{ __('commerce::fulfilment.open_order') }}</x-ui::dropdown-item>
    </x-ui::row-actions>
</div>
