{{--
    Swipeable columns of wide product cards ($rows cards stacked per slide): Flash grid
    (v4 / v6), "New In" (v3 / v5 / v6) and "Trending Now" (v2–v6).
--}}
@props(['cards' => [], 'rows' => 1])
@php($columns = array_chunk($cards, max(1, (int) $rows)))
<div {{ $attributes->class(['elo-rail relative']) }}>
    <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":24}'>
        <div class="swiper-wrapper">
            @foreach ($columns as $column)
                <div class="swiper-slide !h-auto !w-[310px] lg:!w-[461px]" data-reveal="fade-up">
                    <div class="grid gap-4">
                        @foreach ($column as $card)
                            <x-sf::elora.product-card :card="$card" variant="wide" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <button type="button" class="elo-round-btn absolute -start-4 top-1/2 z-10 -translate-y-1/2 max-lg:hidden" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
    <button type="button" class="elo-round-btn absolute -end-4 top-1/2 z-10 -translate-y-1/2 max-lg:hidden" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
</div>
