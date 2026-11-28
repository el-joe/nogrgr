{{--
    Elora v1 product rail (Swiper "rail": touch swipe, mouse drag, trackpad, keys, momentum,
    RTL), clipped to the 1440 page container. kind: tabs (1:15586, no arrows) | section
    (arrows + edge fade, 1:15654) | flash (arrows, 1:15610).
--}}
@props(['cards' => [], 'kind' => 'section', 'delivery' => null, 'label' => null])
@php
    $swiper = match ($kind) {
        'tabs' => ['spaceBetween' => 16, 'slidesOffsetBefore' => 16, 'slidesOffsetAfter' => 16, 'breakpoints' => ['1024' => ['slidesOffsetBefore' => 38, 'slidesOffsetAfter' => 38]]],
        'flash' => ['spaceBetween' => 16, 'slidesOffsetBefore' => 12, 'slidesOffsetAfter' => 12, 'breakpoints' => ['1024' => ['slidesOffsetBefore' => 40, 'slidesOffsetAfter' => 40]]],
        default => ['spaceBetween' => 16, 'slidesOffsetBefore' => 16, 'slidesOffsetAfter' => 16, 'breakpoints' => ['1024' => ['spaceBetween' => 24, 'slidesOffsetBefore' => 0, 'slidesOffsetAfter' => 0]]],
    };
    /* Phones (<768): exactly two cards per view, paged, with dots; from 768 the design's own free-mode rail. */
    $desktop = $swiper;
    $tablet = array_diff_key($desktop, ['breakpoints' => 1]) + ['slidesPerView' => 'auto', 'slidesPerGroup' => 1, 'freeMode' => ['enabled' => true, 'sticky' => true, 'momentumRatio' => 0.6, 'momentumBounceRatio' => 0.4]];
    $swiper = [
        'slidesPerView' => 2,
        'slidesPerGroup' => 2,
        'spaceBetween' => 12,
        'slidesOffsetBefore' => 0,
        'slidesOffsetAfter' => 0,
        'freeMode' => ['enabled' => false],
        'paginationType' => 'bullets',
        'breakpoints' => ['768' => $tablet] + ($desktop['breakpoints'] ?? []),
    ];
@endphp
<div {{ $attributes->class(['v1-rail', 'v1-rail-'.$kind]) }}>
    <div class="swiper" data-rail="{{ json_encode($swiper) }}" @if ($label) aria-label="{{ $label }}" @endif>
        <div class="swiper-wrapper">
            @foreach ($cards as $index => $card)
                <div class="swiper-slide v1-slide">
                    <x-sf::elora.v1.card :card="$card" :delivery="$delivery" :eager="$index < 2" />
                </div>
            @endforeach
        </div>
    </div>
    <div class="v1-dots md:hidden" data-motion-pagination></div>
    @if ($kind !== 'tabs')
        <button type="button" class="v1-arrow v1-arrow-prev" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><span class="v1-ico v1-ico-arrow" aria-hidden="true"></span></button>
        <button type="button" class="v1-arrow v1-arrow-next" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><span class="v1-ico v1-ico-arrow" aria-hidden="true"></span></button>
    @endif
</div>
