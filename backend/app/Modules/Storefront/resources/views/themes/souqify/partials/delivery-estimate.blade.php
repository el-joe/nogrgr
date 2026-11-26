{{--
    Souqify delivery popup (Storefront additions 137:111894 / m(ar) 137:112053; centred
    dialog on desktop, bottom sheet on phones): "Deliver to …" with Change (preferences
    sheet), "Arrives …" estimate, cut-off hint in green, the chance per day as vertical
    columns (admin delivery popup days, A3; the peak day in the accent colour), courier and
    the "estimate, not a guarantee" note. Inputs: $page (ProductPageData), $peak.
--}}
@php
    $days = collect($page->deliveryDays)->values();
@endphp
<x-sf::modal id="sq-delivery" :title="__st('souqify_pages.product.delivery')" size="md">
    <div class="grid gap-4 text-sq-ink">
        @if ($page->delivery['location'] ?? null)
            <div class="flex items-center gap-2 rounded-lg bg-sq-soft px-3 py-2.5 text-sm">
                <x-ui::icon name="map-pin" :size="18" class="text-sq-primary" />
                <span class="flex-1 font-medium">{{ __st('product.deliver_to', ['location' => $page->delivery['location']]) }}</span>
                <button type="button" class="text-xs font-medium text-sq-primary" data-sheet-open="sf-preferences">{{ __st('product.change') }}</button>
            </div>
        @endif
        <p class="text-lg font-semibold">{{ __st('souqify_pages.product.arrives', ['range' => $page->delivery['label']]) }}</p>
        @if ($page->delivery['cutoff_minutes'])
            <p class="text-xs text-sq-green">{{ __st('product.cutoff', ['hours' => intdiv($page->delivery['cutoff_minutes'], 60), 'minutes' => $page->delivery['cutoff_minutes'] % 60]) }}</p>
        @endif
        @if ($days->isNotEmpty())
            <section class="grid gap-2" aria-labelledby="sq-delivery-chance">
                <h3 id="sq-delivery-chance" class="text-xs text-sf-muted">{{ __st('souqify_pages.product.chance_by_day') }}</h3>
                <ol class="sq-day-chart" role="list">
                    @foreach ($days as $row)
                        @php($height = $peak > 0 ? max(4, (int) round($row['percentage'] / $peak * 100)) : 4)
                        <li title="{{ \Carbon\CarbonImmutable::parse($row['date'])->locale(app()->getLocale())->translatedFormat('D j M') }}">
                            <span class="text-[10px] text-sf-muted">{{ $row['percentage'] > 0 ? $row['percentage'].'%' : '' }}</span>
                            <span class="sq-day-col" @if ($row['percentage'] > 0 && $row['percentage'] === $peak) data-peak @elseif ($row['percentage'] > 0) data-on @endif><span data-height="{{ $height }}"></span></span>
                            <span class="text-[10px] text-sf-muted">{{ __st('souqify_pages.product.day_short', ['day' => $row['day']]) }}</span>
                            <span class="sr-only">{{ __st('souqify_pages.product.days', ['count' => $row['day']]) }}: {{ $row['percentage'] }}%</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
        @if ($page->delivery['company'])
            <p class="flex items-center justify-between gap-3 border-t border-sq-line pt-3 text-sm"><span class="text-sf-muted">{{ __st('product.courier_label') }}</span><span class="font-medium">{{ $page->delivery['company'] }}</span></p>
        @endif
        <p class="text-xs leading-relaxed text-sf-muted">{{ __st('product.delivery_note') }}</p>
    </div>
</x-sf::modal>
