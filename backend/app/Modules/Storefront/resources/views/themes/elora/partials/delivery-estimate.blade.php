{{--
    Elora delivery popup (Figma m 1:23053; centred dialog on desktop): delivery time +
    courier table, destination with "change" (preferences sheet), cut-off hint, "Fastest
    delivery in N days" and the chance per day as horizontal bars (admin delivery popup
    days, A3). Inputs: $page (ProductPageData), $peak.
--}}
@php
    $days = collect($page->deliveryDays)->filter(fn ($row) => $row['percentage'] > 0)->values();
    $fastest = $days->first();
@endphp
<x-sf::modal id="elo-delivery" :title="__st('elora_pages.product.delivery')" size="md">
    <div class="grid gap-5 text-elo-ink">
        <dl class="grid grid-cols-[minmax(0,140px)_1fr] overflow-hidden text-base">
            <dt class="bg-[#ededed] px-2 py-1.5">{{ __st('elora_pages.product.delivery_time') }}</dt>
            <dd class="px-3 py-1.5">{{ $page->delivery['label'] }}</dd>
            @if ($page->delivery['company'])
                <dt class="bg-[#ededed] px-2 py-1.5">{{ __st('product.courier_label') }}</dt>
                <dd class="px-3 py-1.5">{{ $page->delivery['company'] }}</dd>
            @endif
        </dl>
        @if ($page->delivery['location'] ?? null)
            <div class="flex items-center gap-2 rounded-lg bg-elo-soft px-3 py-2.5 text-sm">
                <x-ui::icon name="map-pin" :size="18" class="text-elo-primary" />
                <span class="flex-1">{{ __st('product.deliver_to', ['location' => $page->delivery['location']]) }}</span>
                <button type="button" class="text-xs font-medium text-elo-primary" data-sheet-open="sf-preferences">{{ __st('product.change') }}</button>
            </div>
        @endif
        <p class="flex items-start gap-2 text-xs leading-relaxed text-[#5e5e5e]"><x-ui::icon name="info" :size="18" class="shrink-0 text-elo-ink" />{{ __st('product.delivery_note') }}</p>
        @if ($page->delivery['cutoff_minutes'])
            <p class="text-xs text-elo-green">{{ __st('product.cutoff', ['hours' => intdiv($page->delivery['cutoff_minutes'], 60), 'minutes' => $page->delivery['cutoff_minutes'] % 60]) }}</p>
        @endif
        @if ($days->isNotEmpty())
            <section class="grid gap-3" aria-labelledby="elo-delivery-chance">
                <h3 id="elo-delivery-chance" class="text-base font-medium text-elo-primary">{{ __st('elora_pages.product.fastest_in', ['days' => $fastest['day']]) }}</h3>
                <ol class="grid gap-2" role="list">
                    @foreach ($days as $row)
                        <li class="grid grid-cols-[64px_1fr_48px] items-center gap-2 text-xs" title="{{ \Carbon\CarbonImmutable::parse($row['date'])->locale(app()->getLocale())->translatedFormat('D j M') }}">
                            <span>{{ __st('elora_pages.product.days', ['count' => $row['day']]) }}</span>
                            <span class="elo-day-bar" aria-hidden="true"><span data-width="{{ $peak > 0 ? round($row['percentage'] / $peak * 100) : 0 }}"></span></span>
                            <span class="text-end tabular-nums">{{ $row['percentage'] }} %</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-sf::modal>
