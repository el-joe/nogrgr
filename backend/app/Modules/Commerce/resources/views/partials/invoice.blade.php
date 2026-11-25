{{-- Printable order receipt / invoice (admin receipt, vendor "Print invoice"). --}}
@php
    $address = $order->shipping_address ?? [];
    $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? null;
@endphp
<article class="mx-auto w-full max-w-[840px] rounded-card border border-line bg-surface p-8 shadow-card print:max-w-none print:border-0 print:p-0 print:shadow-none" data-print-sheet data-autoprint>
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-ink-secondary">{{ $store }}</p>
            <h1 class="text-2xl font-semibold text-ink" dir="ltr">#{{ $order->number }}</h1>
            <p class="text-sm text-ink-secondary">{{ $order->placed_at?->translatedFormat('j M Y, H:i') }}</p>
        </div>
        <div class="text-end">
            @include('commerce::partials.status-badge', ['value' => $order->status])
            <p class="mt-1 text-sm text-ink-secondary">{{ $order->payment_status->label() }} · {{ \App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($order->payment_gateway) }}</p>
        </div>
    </header>
    <section class="mb-6 grid gap-4 sm:grid-cols-2">
        <div>
            <h2 class="mb-1 text-xs font-semibold uppercase text-ink-secondary">{{ __('commerce::orders_admin.customer') }}</h2>
            <p class="text-sm text-ink">{{ $order->customer_name }}</p>
            <p class="text-sm text-ink-secondary" dir="ltr">{{ $order->customer_email }}</p>
        </div>
        <div>
            <h2 class="mb-1 text-xs font-semibold uppercase text-ink-secondary">{{ __('commerce::orders_admin.shipping_address') }}</h2>
            <address class="text-sm not-italic text-ink">{{ collect([$address['full_name'] ?? null, $address['address_line1'] ?? null, $address['city'] ?? null, $country])->filter()->implode(', ') ?: '—' }}</address>
        </div>
    </section>
    <section class="mb-6">@include('commerce::partials.items', ['order' => $order])</section>
    <section class="ms-auto max-w-sm">@include('commerce::partials.totals', ['order' => $order])</section>
</article>
