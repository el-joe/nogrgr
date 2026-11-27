{{--
    Order lines: thumbnail, name, variant · SKU, qty × price, line total. RQA-VEN-006: the
    line total (grand_total) includes the line's fixed shipping (per line, never × qty),
    flash discount and tax (ORDER_FORMULAS.md), so those parts are listed under the
    "qty × price" figure and the line reads as a sum that adds up.
--}}
<ul class="divide-y divide-line">
    @foreach ($order->items as $item)
        @php
            $lineMoney = static fn (?int $minor): \App\Shared\Money\Money => \App\Shared\Money\Money::ofMinor((int) $minor, $order->currency);
            $lineParts = array_filter([
                'fixed_shipping' => $item->fixed_shipping > 0 ? '+ '.$lineMoney($item->fixed_shipping)->format() : null,
                'discount' => $item->discount > 0 ? '− '.$lineMoney($item->discount)->format() : null,
                'tax' => $item->tax > 0 ? '+ '.$lineMoney($item->tax)->format() : null,
                'shipping_fee' => $item->shipping_fee > 0 ? '+ '.$lineMoney($item->shipping_fee)->format() : null,
            ]);
        @endphp
        <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0" data-order-line>
            @if ($item->image_url)
                <img src="{{ $item->image_url }}" alt="" class="size-12 shrink-0 rounded-control object-contain bg-media" loading="lazy">
            @else
                <span class="grid size-12 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="box" :size="20" /></span>
            @endif
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-ink">{{ $item->name }}</p>
                <p class="truncate text-xs text-ink-secondary">{{ collect([$item->variant_label, $item->sku])->filter()->implode(' · ') }}</p>
            </div>
            <div class="shrink-0 text-end text-sm text-ink-secondary">
                <p dir="ltr">{{ $item->quantity }} × {{ $lineMoney($item->price)->format() }}</p>
                @foreach ($lineParts as $part => $amount)
                    <p class="text-xs" data-line-part="{{ $part }}">{{ __('commerce::orders_admin.line_parts.'.$part) }} <span dir="ltr">{{ $amount }}</span></p>
                @endforeach
            </div>
            <p class="w-24 shrink-0 text-end font-semibold text-ink" dir="ltr">{{ $lineMoney($item->grand_total)->format() }}</p>
        </li>
    @endforeach
</ul>
