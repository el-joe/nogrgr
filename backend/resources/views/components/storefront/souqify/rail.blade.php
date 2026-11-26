{{--
    Souqify product rail (Swiper "rail": touch swipe, mouse drag, trackpad / Shift+wheel, keys,
    momentum + snap, rubber-band ends, RTL) with round prev / next buttons on desktop. Cards:
    x-sf::souqify.product-card (variant card | wide | side); $rows > 1 stacks that many cards
    per slide (two-row / three-row rails of the Figma).
--}}
@props(['cards' => [], 'variant' => 'card', 'rows' => 1, 'slide' => null, 'label' => null, 'tone' => 'orange', 'progress' => false])
@php
    $slide ??= match ($variant) {
        'wide' => '!w-[300px] lg:!w-[448px]',
        'side' => '!w-[330px] lg:!w-[472px]',
        default => 'sq-rail-slide',
    };
    $groups = array_chunk($cards, max(1, (int) $rows));
@endphp
<div {{ $attributes->class(['sq-rail group/rail relative']) }}>
    <div class="swiper !overflow-visible" data-rail='{"spaceBetween":{{ $variant === 'card' ? 24 : 20 }}}' @if ($label) aria-label="{{ $label }}" @endif>
        <div class="swiper-wrapper">
            @foreach ($groups as $index => $group)
                <div class="swiper-slide !h-auto {{ $slide }}" data-reveal="fade-up">
                    <div @class(['grid h-full gap-4 max-lg:gap-3', 'content-start' => count($group) > 1])>
                        @foreach ($group as $card)
                            <x-sf::souqify.product-card :card="$card" :variant="$variant" :tone="$tone" :progress="$progress" :eager="$index < 2" class="h-full" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <button type="button" class="sq-round-btn absolute -start-5 top-[40%] z-10 max-lg:hidden" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
    <button type="button" class="sq-round-btn absolute -end-5 top-[40%] z-10 max-lg:hidden" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
</div>
