{{--
    Elora wide-card section ("Trending Now" v2–v6, "New In" v3 / v5 / v6): heading + swipeable
    columns of horizontal cards. rows: 1 | 2 | 3 | mixed (v6: a tall card then a pair of wide cards).
--}}
@props(['cards' => [], 'section' => []])
@if ($cards !== [])
    @php
        $title = $section['title_text'] ?? __st($section['title']);
        $more = isset($section['more']) && Route::has($section['more']) ? route($section['more']) : null;
        $band = $section['band'] ?? null;
        $rows = $section['rows'] ?? '1';
    @endphp
    <section @class(['elo-section', 'elo-band-'.$band => $band]) aria-label="{{ $title }}">
        <div class="elo-home-wrap">
            <x-sf::elora.heading :title="$title" :subtitle="$section['subtitle_text'] ?? null" :more="$more" class="mb-6 max-lg:mb-4" />
            @if ($rows === 'mixed')
                @php($groups = array_chunk($cards, 3))
                <div class="elo-rail relative">
                    <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":16}'>
                        <div class="swiper-wrapper">
                            @foreach ($groups as $group)
                                <div class="swiper-slide !h-auto !w-[160px] lg:!w-[190px]" data-reveal="fade-up"><x-sf::elora.product-card :card="$group[0]" class="h-full" /></div>
                                @if (count($group) > 1)
                                    <div class="swiper-slide !h-auto !w-[310px] lg:!w-[425px]" data-reveal="fade-up">
                                        <div class="grid gap-4">
                                            @foreach (array_slice($group, 1) as $card)<x-sf::elora.product-card :card="$card" variant="wide" />@endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <x-sf::elora.wide-grid :cards="$cards" :rows="(int) $rows" />
            @endif
        </div>
    </section>
@endif
