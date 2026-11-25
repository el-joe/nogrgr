{{--
    ADM-021 receipt: print-optimised (resources/css/panels/print.css), the print dialog
    opens on load; "Save as PDF" in the dialog makes the PDF (no PDF library, decision #46).
--}}
<x-layouts.panel surface="admin" :title="__('commerce::orders_admin.receipt_title', ['number' => $order->number])" data-page="admin-orders">
    <div class="flex flex-wrap items-center justify-between gap-3" data-print-hide>
        <x-ui::button variant="secondary" icon="chevron-left" :href="route('admin.orders.show', $fact->id)" class="rtl:[&_svg]:rotate-180">{{ __('commerce::orders_admin.back_to_order') }}</x-ui::button>
        <x-ui::button icon="printer" data-print>{{ __('commerce::orders_admin.print') }}</x-ui::button>
    </div>
    @include('commerce::partials.invoice', ['order' => $order, 'store' => $context->storeName])
</x-layouts.panel>
