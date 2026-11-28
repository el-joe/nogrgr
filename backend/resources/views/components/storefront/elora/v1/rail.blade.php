{{--
    Elora v1 product rail (Swiper "rail": touch swipe, mouse drag, trackpad, keys, momentum,
    RTL), clipped to the 1440 page container. kind: tabs (1:15586, no arrows) | section
    (arrows + edge fade, 1:15654) | flash (arrows, 1:15610).
--}}
@props(['cards' => [], 'kind' => 'section', 'delivery' => null, 'label' => null])
@php
    /* Cards per view (same steps as the product page rail): phones (<520) exactly 2, paged, dots, 12px gap;
       520+ free-mode rails with 2.5 / 3.2, tablet 3 -> 3.8, laptop 4 -> 4.5, 1280+ 5.9 (210px Figma cards, 6th clipped).
       Swiper breakpoints do not stack, so every step carries the full set (gap, offsets, free mode). */
    $free = ['enabled' => true, 'sticky' => false, 'momentumRatio' => 0.6, 'momentumBounceRatio' => 0.4];
    [$smallEdge, $wideEdge] = match ($kind) {
        'tabs' => [16, 38],
        'flash' => [12, 40],
        default => [16, 0],
    };
    $step = static fn (float $view, int $gap, int $edge) => ['slidesPerView' => $view, 'slidesPerGroup' => 1, 'spaceBetween' => $gap, 'slidesOffsetBefore' => $edge, 'slidesOffsetAfter' => $edge, 'freeMode' => $free];
    $swiper = [
        'slidesPerView' => 2,
        'slidesPerGroup' => 2,
        'spaceBetween' => 12,
        'slidesOffsetBefore' => 0,
        'slidesOffsetAfter' => 0,
        'freeMode' => ['enabled' => false],
        'paginationType' => 'bullets',
        'breakpoints' => [
            '520' => $step(2.5, 16, 0),
            '640' => $step(3.2, 16, 0),
            '768' => $step(3, 16, $smallEdge),
            '840' => $step(3.4, 16, $smallEdge),
            '920' => $step(3.8, 16, $smallEdge),
            '1000' => $step(4, 16, $smallEdge),
            '1024' => $step(4, 24, $wideEdge),
            '1150' => $step(4.5, 24, $wideEdge),
            '1280' => $step(5.9, 24, $wideEdge),
        ],
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
