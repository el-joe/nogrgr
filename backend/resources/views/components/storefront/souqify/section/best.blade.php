{{--
    Souqify "Best Seller" band (v2–v6).
      groups     v2: gradient band — slides of [large card + three wide cards], pill pagination
      rail       v3: teal band — centred title, card rail, "Explore all"
      spotlight  v4: green band — progressive left spotlight slider, pagination
      fan        v5: orange scalloped band — tilted centred card fan
      stagger    v6: pink scalloped band — rail with alternating card offsets
    Hidden when there are no best sellers.
--}}
@props(['cards' => [], 'section' => []])
@if ($cards !== [])
    @php
        $style = $section['style'] ?? 'rail';
        $title = $section['title_text'] ?? __st('souqify.home.best_seller');
        $more = isset($section['more']) && Route::has($section['more']) ? route($section['more']) : null;
    @endphp
    <section @class(['sq-best sq-section', 'sq-best-'.$style]) aria-label="{{ $title }}">
        <div class="sq-hwrap">
            <x-sf::souqify.heading :title="$title" :subtitle="$section['subtitle_text'] ?? null" :more="$style === 'rail' ? null : $more" :center="$style === 'rail'" tone="light" class="mb-6 max-lg:mb-3" />
            @if ($style === 'groups')
                @php
                    $groups = [];
                    $rest = $cards;
                    while ($rest !== []) {
                        $groups[] = array_splice($rest, 0, 4);
                    }
                @endphp
                <div class="relative">
                    <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":16}' aria-label="{{ $title }}">
                        <div class="swiper-wrapper">
                            @foreach ($groups as $group)
                                <div class="swiper-slide !h-auto !w-[300px] lg:!w-[calc(50%-8px)]" data-reveal="fade-up">
                                    <div class="grid h-full gap-3 lg:grid-cols-[minmax(0,206px)_minmax(0,1fr)]">
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
                    <div class="sq-dots mt-5"><div class="swiper-pagination !static" data-motion-pagination></div></div>
                </div>
            @elseif ($style === 'spotlight' || $style === 'fan')
                <x-sf::souqify.spotlight :cards="$cards" :layout="$style === 'fan' ? 'fan' : 'left'" />
            @else
                <x-sf::souqify.rail :cards="$cards" :label="$title" :class="$style === 'stagger' ? 'sq-rail-stagger' : ''" />
                @if ($style === 'rail' && $more)
                    <div class="mt-6 flex justify-center"><a href="{{ $more }}" class="sq-outline-btn sq-outline-light mo-press">{{ __st('souqify.home.explore_all') }}</a></div>
                @endif
            @endif
        </div>
    </section>
@endif
