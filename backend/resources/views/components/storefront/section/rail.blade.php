{{--
    Horizontal product rail (Swiper "rail": touch swipe, mouse drag, trackpad, keys, RTL)
    with prev / next buttons on desktop. Cards: x-sf::product-card variant.
--}}
@props(['cards' => [], 'variant' => 'grid', 'slideClass' => '!w-[164px] lg:!w-[224px]', 'label' => null])
<div {{ $attributes->class(['group/rail relative']) }}>
    <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":16}' @if ($label) aria-label="{{ $label }}" @endif>
        <div class="swiper-wrapper">
            @foreach ($cards as $index => $card)
                <div class="swiper-slide !h-auto {{ $slideClass }}" data-reveal="fade-up">
                    <x-sf::product-card :card="$card" :variant="$variant" :eager="$index < 2" />
                </div>
            @endforeach
        </div>
    </div>
    <button type="button" class="absolute -start-5 top-[40%] z-10 hidden size-10 place-items-center rounded-full bg-white text-eco-black shadow-md transition disabled:opacity-0 lg:grid" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
    <button type="button" class="absolute -end-5 top-[40%] z-10 hidden size-10 place-items-center rounded-full bg-white text-eco-black shadow-md transition disabled:opacity-0 lg:grid" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
</div>
