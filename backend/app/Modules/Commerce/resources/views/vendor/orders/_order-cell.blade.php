@php $first = $order->items->first(); @endphp
<a href="{{ vendor_route('sales.orders.show', ['order' => $order->uuid]) }}" class="flex items-center gap-3">
    @if ($first?->image_url)
        <img src="{{ $first->image_url }}" alt="" class="size-9 shrink-0 rounded-control object-contain bg-media" loading="lazy">
    @else
        <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="box" :size="18" /></span>
    @endif
    <span class="min-w-0">
        <span class="block max-w-48 truncate text-sm text-ink">{{ $first?->name ?? '—' }}@if ($order->items->count() > 1) <span class="text-ink-secondary">+{{ $order->items->count() - 1 }}</span>@endif</span>
        <span class="text-xs text-ink-secondary" dir="ltr">#{{ $order->number }}</span>
    </span>
</a>
