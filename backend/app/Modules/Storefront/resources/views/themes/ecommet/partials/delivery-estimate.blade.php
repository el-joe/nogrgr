{{--
    Delivery estimate body (Storefront additions 137:111644 desktop popover / m 137:111815
    sheet): destination + change, arrival range, cut-off hint, delivery chance by day
    (admin "delivery popup days", 0 % days hidden as empty bars), courier, disclaimer.
--}}
<div class="grid gap-4 text-eco-black">
    <div class="flex items-center gap-2 rounded-lg bg-[#f6f6f6] px-4 py-3 text-sm">
        <x-ui::icon name="map-pin" :size="18" class="text-eco-red" />
        <span class="flex-1 font-medium">{{ __st('product.deliver_to', ['location' => $page->delivery['location'] ?? '']) }}</span>
        <button type="button" class="text-xs font-medium text-eco-red" data-sheet-open="sf-preferences">{{ __st('product.change') }}</button>
    </div>
    <p class="text-lg font-semibold">{{ __st('product.arrives', ['range' => $page->delivery['label']]) }}</p>
    @if ($page->delivery['cutoff_minutes'])
        <p class="text-xs text-eco-green">{{ __st('product.cutoff', ['hours' => intdiv($page->delivery['cutoff_minutes'], 60), 'minutes' => $page->delivery['cutoff_minutes'] % 60]) }}</p>
    @endif
    @if ($page->deliveryDays !== [])
        <div class="grid gap-2">
            <p class="text-xs text-eco-gray">{{ __st('product.delivery_chance') }}</p>
            <ol class="flex h-[118px] items-end justify-between gap-1" role="list" data-delivery-bars>
                @for ($day = 1; $day <= 10; $day++)
                    @php($row = collect($page->deliveryDays)->firstWhere('day', $day))
                    @php($pct = $row['percentage'] ?? 0)
                    <li class="flex flex-1 flex-col items-center justify-end gap-1 text-[11px]" @if ($row) title="{{ \Carbon\CarbonImmutable::parse($row['date'])->locale(app()->getLocale())->translatedFormat('D j M') }}" @endif>
                        @if ($pct > 0)<span class="text-eco-gray">{{ $pct }}%</span>@endif
                        <span @class(['eco-bar', 'is-peak' => $pct > 0 && $pct >= $peak * 0.6]) data-bar="{{ $pct }}" data-bar-max="{{ $peak }}" aria-hidden="true"></span>
                        <span @class(['font-medium text-eco-black' => $pct > 0 && $pct >= $peak * 0.6, 'text-eco-gray' => ! ($pct > 0 && $pct >= $peak * 0.6)])>{{ __st('product.day_short', ['day' => $day]) }}</span>
                        <span class="sr-only">{{ $pct }}%</span>
                    </li>
                @endfor
            </ol>
        </div>
    @endif
    @if ($page->delivery['company'])
        <div class="flex items-center justify-between text-sm"><span class="text-eco-gray">{{ __st('product.courier_label') }}</span><span class="font-medium">{{ $page->delivery['company'] }}</span></div>
    @endif
    <p class="text-xs text-eco-gray">{{ __st('product.delivery_note') }}</p>
</div>
