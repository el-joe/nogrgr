{{-- Shipping label (ADM-022a "Print label"): print view, opens the print dialog on load. --}}
@php
    $address = $order->shipping_address ?? [];
    $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? null;
@endphp
<x-layouts.panel surface="admin" :title="__('commerce::fulfilment.label_title', ['number' => $order->number])" data-page="admin-orders">
    <div class="flex flex-wrap items-center justify-between gap-3" data-print-hide>
        <x-ui::button variant="secondary" icon="chevron-left" :href="route('admin.shipping-queue.show', $fact->id)" class="rtl:[&_svg]:rotate-180">{{ __('commerce::fulfilment.back_to_shipment') }}</x-ui::button>
        <x-ui::button icon="printer" data-print>{{ __('commerce::orders_admin.print') }}</x-ui::button>
    </div>
    <article class="mx-auto grid w-full max-w-[560px] gap-5 rounded-card border-2 border-ink bg-surface p-6 print:border-ink" data-print-sheet data-autoprint>
        <header class="flex items-start justify-between gap-4 border-b border-line pb-4">
            <div>
                <p class="text-xs uppercase text-ink-secondary">{{ __('commerce::fulfilment.fields.company') }}</p>
                <p class="text-xl font-semibold text-ink">{{ $order->shipping_company_name ?? '—' }}</p>
            </div>
            <div class="text-end">
                <p class="text-xs uppercase text-ink-secondary">{{ __('commerce::fulfilment.fields.tracking') }}</p>
                <p class="font-mono text-lg font-semibold text-ink" dir="ltr">{{ $order->tracking_number ?? __('commerce::fulfilment.queue.awb_pending') }}</p>
            </div>
        </header>
        <section>
            <p class="text-xs uppercase text-ink-secondary">{{ __('commerce::fulfilment.ship_to') }}</p>
            <p class="text-lg font-semibold text-ink">{{ $address['full_name'] ?? $order->customer_name }}</p>
            <p class="text-ink">{{ $address['address_line1'] ?? '' }}</p>
            <p class="text-ink">{{ collect([$address['city'] ?? null, $address['state'] ?? null, $country])->filter()->implode(', ') }}</p>
            <p class="text-ink" dir="ltr">{{ $address['phone'] ?? $order->customer_phone }}</p>
        </section>
        <section class="grid grid-cols-3 gap-3 border-t border-line pt-4 text-sm">
            <div><p class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.label.order') }}</p><p class="font-semibold text-ink" dir="ltr">#{{ $order->number }}</p></div>
            <div><p class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.label.pieces') }}</p><p class="font-semibold text-ink">{{ $order->items->sum('quantity') }}</p></div>
            <div><p class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.label.weight') }}</p><p class="font-semibold text-ink">{{ number_format($order->items->sum('weight_grams')) }} g</p></div>
            <div><p class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.label.from') }}</p><p class="font-semibold text-ink">{{ $order->fulfilment_branch_id ? ($branches[$order->fulfilment_branch_id] ?? '—') : $context->storeName }}</p></div>
            <div><p class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.label.store') }}</p><p class="font-semibold text-ink">{{ $context->storeName }}</p></div>
            <div><p class="text-xs text-ink-secondary">{{ __('commerce::fulfilment.label.cod') }}</p><p class="font-semibold text-ink" dir="ltr">{{ $order->payment_status === \App\Modules\Commerce\Enums\PaymentStatus::CashOnDelivery ? $order->money('grand_total')->format() : '—' }}</p></div>
        </section>
    </article>
</x-layouts.panel>
