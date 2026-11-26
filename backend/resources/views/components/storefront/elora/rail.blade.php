{{--
    Elora product rail (Swiper "rail": touch swipe, mouse drag, trackpad, keys, momentum +
    snap, RTL) with round prev / next buttons on desktop (Figma vuesax arrows over the edge
    cards). Cards: x-sf::elora.product-card (variant card | wide).
--}}
@props(['cards' => [], 'variant' => 'card', 'slide' => null, 'label' => null])
@php($slide ??= $variant === 'wide' ? '!w-[300px] lg:!w-[461px]' : '!w-[160px] lg:!w-[210px]')
<div {{ $attributes->class(['elo-rail group/rail relative']) }}>
    <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":16}' @if ($label) aria-label="{{ $label }}" @endif>
        <div class="swiper-wrapper">
            @foreach ($cards as $index => $card)
                <div class="swiper-slide !h-auto {{ $slide }}" data-reveal="fade-up">
                    <x-sf::elora.product-card :card="$card" :variant="$variant" :eager="$index < 2" class="h-full" />
                </div>
            @endforeach
        </div>
    </div>
    <button type="button" class="elo-round-btn absolute -start-4 top-[38%] z-10 max-lg:hidden" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
    <button type="button" class="elo-round-btn absolute -end-4 top-[38%] z-10 max-lg:hidden" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
</div>
