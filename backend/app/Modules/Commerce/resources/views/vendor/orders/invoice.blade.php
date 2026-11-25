{{-- VEN-021 "Print invoice": print-optimised order invoice (browser print / Save as PDF). --}}
<x-vendor::layout :title="__('commerce::orders_admin.receipt_title', ['number' => $order->number])" page="vendor-orders">
    <div class="flex flex-wrap items-center justify-between gap-3" data-print-hide>
        <x-ui::button variant="secondary" icon="chevron-left" :href="vendor_route('sales.orders.show', ['order' => $order->uuid])" class="rtl:[&_svg]:rotate-180">{{ __('commerce::orders_admin.back_to_order') }}</x-ui::button>
        <x-ui::button icon="printer" data-print>{{ __('commerce::orders_admin.print') }}</x-ui::button>
    </div>
    @include('commerce::partials.invoice', ['order' => $order, 'store' => $store])
</x-vendor::layout>
