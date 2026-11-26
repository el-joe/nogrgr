{{-- Order column of the customer's orders (VEN-025b): number (links to VEN-021 with orders.view) and date. --}}
<div class="grid gap-0.5">
    @if ($link)
        <a href="{{ vendor_route('sales.orders.show', ['order' => $order->uuid]) }}" class="font-semibold text-ink hover:text-ink-brand" dir="ltr">#{{ $order->number }}</a>
    @else
        <span class="font-semibold text-ink" dir="ltr">#{{ $order->number }}</span>
    @endif
    <span class="text-xs text-ink-secondary">{{ $order->placed_at?->translatedFormat('d M Y') }}</span>
</div>
