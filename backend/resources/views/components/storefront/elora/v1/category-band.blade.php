{{-- Elora v1 orange category band (1:15667, desktop): one swipeable row of round photos. --}}
@props(['tiles' => []])
@if ($tiles !== [])
    <section {{ $attributes->class(['v1-band v1-grad-bg']) }} aria-label="{{ __st('elora.home.categories') }}">
        <div class="v1-wrap">
            <div class="swiper" data-rail='{"spaceBetween":32,"centerInsufficientSlides":true}'>
                <div class="swiper-wrapper">
                    @foreach ($tiles as $tile)
                        <div class="swiper-slide v1-band-slide"><x-sf::elora.v1.circle :tile="$tile" /></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
