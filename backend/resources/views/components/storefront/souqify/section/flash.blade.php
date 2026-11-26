{{--
    Souqify flash sale (live sale of the shopping country that ends first + its products).
      deck       v1 1:72860: amber band — "Today's Flash Sale", "Ends in" hour / minute / second
                 tiles, side deal cards around one featured deal card (phone: side-card rail)
      rail       v2: dark striped band — card rail, vertical "Ends in" + boxes, "Shop now" pill
      stack      v3: teal band — vertical title + countdown at the start, rail of [large card +
                 three wide cards] groups, "Shop now"
      fan        v4: green ray band — centred title with bolt, vertical countdown, tilted card fan
                 (spotlight, old-system-docs/home-pages-custom-sliders.md), "Shop now"
      cards      v5: orange band — two-row rail of deal side cards, "Shop now" + countdown
      spotlight  v6: accent pill title + round countdown, white panel, progressive left
                 spotlight slider, outline "Shop now"
    Hidden without a live sale or without sellable deal products.
--}}
@props(['sale' => null, 'deals' => [], 'style' => 'deck'])
@if ($sale && $deals !== [])
    @php
        $more = Route::has('storefront.offers') ? route('storefront.offers', ['flash' => 1]) : null;
        $title = $sale->title !== '' ? $sale->title : __st('souqify.flash.title');
    @endphp
    <section @class(['sq-flash', 'sq-flash-'.$style]) aria-labelledby="sq-flash-title">
        @if ($style === 'deck')
            <div class="sq-hwrap">
                <div class="mb-9 flex flex-wrap items-center justify-between gap-6 max-lg:mb-4 max-lg:gap-2" data-reveal="fade-up">
                    <div class="flex flex-wrap items-center gap-7 max-lg:gap-3">
                        <h2 id="sq-flash-title" class="sq-flash-title">{{ $title }}</h2>
                        <span class="text-5xl font-medium text-[#242424] max-lg:hidden">{{ __st('souqify.flash.ends_in') }}</span>
                        <x-sf::souqify.countdown :ends-at="$sale->endsAt" style="boxes" labels />
                    </div>
                    @if ($more)<a href="{{ $more }}" class="sq-see-all sq-see-all-lg">{{ __st('souqify.home.view_all') }}<x-ui::icon name="arrow-right" :size="20" class="rtl:-scale-x-100" /></a>@endif
                </div>
                @php
                    $featured = $deals[0];
                    $start = array_slice($deals, 1, 3);
                    $end = array_slice($deals, 4, 3);
                @endphp
                <div class="grid items-center gap-8 max-lg:hidden lg:grid-cols-[minmax(0,472fr)_minmax(0,368fr)_minmax(0,472fr)]">
                    <div class="grid gap-5" data-reveal-stagger>
                        @foreach ($start as $card)<x-sf::souqify.product-card :card="$card" variant="side" data-reveal="slide-start" />@endforeach
                    </div>
                    <x-sf::souqify.product-card :card="$featured" variant="deal" data-reveal="zoom" />
                    <div class="grid gap-5" data-reveal-stagger>
                        @foreach ($end as $card)<x-sf::souqify.product-card :card="$card" variant="side" data-reveal="fade-up" />@endforeach
                    </div>
                </div>
                <x-sf::souqify.rail :cards="$deals" variant="side" :label="$title" class="lg:hidden" />
            </div>
        @elseif ($style === 'rail' || $style === 'cards')
            <div class="sq-hwrap">
                <x-sf::souqify.heading id="sq-flash-title" :title="$title" :more="$more" tone="light" class="mb-6 max-lg:mb-3" />
                @if ($style === 'rail')
                    <x-sf::souqify.rail :cards="$deals" :label="$title" />
                @else
                    <x-sf::souqify.rail :cards="$deals" variant="side" tone="accent" :rows="2" :label="$title" />
                @endif
                <div @class(['mt-6 flex items-center gap-6 max-lg:mt-4 max-lg:gap-3', 'flex-row-reverse justify-between' => $style === 'cards'])>
                    <div class="flex items-center gap-2">
                        <span class="sq-flash-ends">{{ __st('souqify.flash.ends_in') }}</span>
                        <x-sf::souqify.countdown :ends-at="$sale->endsAt" />
                    </div>
                    @if ($more)<a href="{{ $more }}" @class(['sq-pill-btn mo-press', 'flex-1' => $style === 'rail'])>{{ __st('souqify.home.shop_now') }}</a>@endif
                </div>
            </div>
        @elseif ($style === 'stack')
            @php
                $groups = [];
                $rest = $deals;
                while ($rest !== []) {
                    $groups[] = array_splice($rest, 0, 4);
                }
            @endphp
            <div class="sq-hwrap grid items-center gap-8 lg:grid-cols-[auto_minmax(0,1fr)]">
                <div class="flex items-center gap-6 lg:flex-col-reverse lg:items-center max-lg:justify-between">
                    <h2 id="sq-flash-title" class="sq-flash-vertical">{{ $title }}</h2>
                    <div class="flex items-center gap-3 lg:flex-col-reverse">
                        <span class="sq-flash-ends text-[#ffd428]">{{ __st('souqify.flash.ends_in') }}</span>
                        <x-sf::souqify.countdown :ends-at="$sale->endsAt" vertical />
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="swiper !overflow-visible" data-rail='{"spaceBetween":20}' aria-label="{{ $title }}">
                        <div class="swiper-wrapper">
                            @foreach ($groups as $group)
                                <div class="swiper-slide !h-auto !w-[300px] lg:!w-[700px]" data-reveal="fade-up">
                                    <div class="grid h-full gap-4 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
                                        <x-sf::souqify.product-card :card="$group[0]" class="h-full" />
                                        @if (count($group) > 1)
                                            <div class="grid content-start gap-3 max-lg:hidden">
                                                @foreach (array_slice($group, 1) as $card)<x-sf::souqify.product-card :card="$card" variant="wide" />@endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($more)<div class="mt-6 flex justify-center"><a href="{{ $more }}" class="sq-pill-btn sq-pill-btn-sm mo-press">{{ __st('souqify.home.shop_now') }}</a></div>@endif
                </div>
            </div>
        @else
            <div class="sq-hwrap">
                @if ($style === 'fan')
                    <h2 id="sq-flash-title" class="sq-flash-center" data-reveal="fade-up"><x-ui::icon name="zap" :size="56" class="text-[#ffd428] max-lg:size-8" />{{ $title }}</h2>
                @else
                    <div class="sq-flash-pill" data-reveal="slide-start">
                        <h2 id="sq-flash-title">{{ $title }}</h2>
                        <span class="sq-flash-ends">{{ __st('souqify.flash.ends_in') }}</span>
                        <x-sf::souqify.countdown :ends-at="$sale->endsAt" style="round" />
                    </div>
                @endif
                <div @class(['grid items-center gap-6', 'lg:grid-cols-[auto_minmax(0,1fr)]' => $style === 'fan', 'sq-flash-panel' => $style === 'spotlight'])>
                    @if ($style === 'fan')
                        <div class="flex items-center gap-3 lg:flex-col-reverse max-lg:justify-center">
                            <span class="sq-flash-ends text-[#ffd428]">{{ __st('souqify.flash.ends_in') }}</span>
                            <x-sf::souqify.countdown :ends-at="$sale->endsAt" vertical />
                        </div>
                    @endif
                    <x-sf::souqify.spotlight :cards="$deals" :layout="$style === 'fan' ? 'fan' : 'left'" />
                    @if ($more)<div @class(['flex justify-center', 'lg:col-span-2' => $style === 'fan'])><a href="{{ $more }}" @class(['sq-pill-btn sq-pill-btn-sm mo-press', 'sq-pill-outline' => $style === 'spotlight'])>{{ __st('souqify.home.shop_now') }}</a></div>@endif
                </div>
            </div>
        @endif
    </section>
@endif
