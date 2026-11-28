{{--
    Elora v1 product page rail (Figma 1:16255 / 1:16267 / 1:16279, m 1:21935): left-aligned
    title ending in " :", "see all" at the end, cards 210 wide on desktop (the 6th is clipped by the
    1360 frame, a next arrow floats over it) and 132 wide on phones. Swiper "rail": touch swipe,
    mouse drag, trackpad, keys, momentum, RTL.
--}}
@props(['cards' => [], 'title', 'more' => null])
@php
    /* Cards per view: phones (<768) exactly 2, paged, dots, 12px gap (same setup as the home rails); then free-mode
       rails with 3 (tablet), 4 (small laptop) and 5.9 (1280+: 210px Figma cards, 6th clipped); never wider than ~250px. */
    $free = ['enabled' => true, 'sticky' => false, 'momentumRatio' => 0.6, 'momentumBounceRatio' => 0.4];
    /* Swiper breakpoints do not stack: every step carries the full free-mode set. */
    $step = static fn (float $view, int $gap) => ['slidesPerView' => $view, 'slidesPerGroup' => 1, 'spaceBetween' => $gap, 'freeMode' => $free];
    $swiper = [
        'slidesPerView' => 2,
        'slidesPerGroup' => 2,
        'spaceBetween' => 12,
        'freeMode' => ['enabled' => false],
        'paginationType' => 'bullets',
        'breakpoints' => [
            '520' => $step(2.5, 16),
            '640' => $step(3.2, 16),
            '768' => $step(3, 16),
            '840' => $step(3.4, 16),
            '920' => $step(3.8, 16),
            '1000' => $step(4, 16),
            '1024' => $step(4, 24),
            '1150' => $step(4.5, 24),
            '1280' => $step(5.9, 24),
        ],
    ];
@endphp
@if ($cards !== [])
    <section class="epd-rail-sec" aria-label="{{ $title }}">
        <div class="epd-rail-head" data-reveal="fade-up">
            <h2>{{ $title }}</h2>
            @if ($more)
                <a href="{{ $more }}">{{ __st('elora_pages.product.see_all') }}</a>
            @endif
        </div>
        <div class="epd-rail" data-reveal="fade-up">
            <div class="swiper" data-rail="{{ json_encode($swiper) }}" aria-label="{{ $title }}">
                <div class="swiper-wrapper">
                    @foreach ($cards as $index => $card)
                        <div class="swiper-slide epd-slide">
                            <x-sf::elora.v1.card :card="$card" :eager="$index < 2" />
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="v1-dots md:hidden" data-motion-pagination></div>
            <button type="button" class="v1-arrow v1-arrow-prev" data-motion-prev aria-label="{{ __st('catalog.rail.previous') }}"><span class="v1-ico v1-ico-arrow" aria-hidden="true"></span></button>
            <button type="button" class="v1-arrow v1-arrow-next" data-motion-next aria-label="{{ __st('catalog.rail.next') }}"><span class="v1-ico v1-ico-arrow" aria-hidden="true"></span></button>
        </div>
    </section>
@endif
