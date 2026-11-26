{{-- Order lines: thumbnail, name, variant · SKU, qty × price, line total. --}}
<ul class="divide-y divide-line">
    @foreach ($order->items as $item)
        <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
            @if ($item->image_url)
                <img src="{{ $item->image_url }}" alt="" class="size-12 shrink-0 rounded-control object-contain bg-media" loading="lazy">
            @else
                <span class="grid size-12 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="box" :size="20" /></span>
            @endif
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-ink">{{ $item->name }}</p>
                <p class="truncate text-xs text-ink-secondary">{{ collect([$item->variant_label, $item->sku])->filter()->implode(' · ') }}</p>
            </div>
            <p class="shrink-0 text-sm text-ink-secondary" dir="ltr">{{ $item->quantity }} × {{ \App\Shared\Money\Money::ofMinor($item->price, $order->currency)->format() }}</p>
            <p class="w-24 shrink-0 text-end font-semibold text-ink" dir="ltr">{{ \App\Shared\Money\Money::ofMinor($item->grand_total, $order->currency)->format() }}</p>
        </li>
    @endforeach
</ul>
