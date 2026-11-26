{{--
    Souqify promo pair (v1 1:73196: two 670×309 rounded banners — eyebrow, title, white CTA).
    Uses the 'home.promo' banners after the two hero side banners; phone: swipeable.
--}}
@props(['banners' => []])
@if ($banners !== [])
    <section class="sq-section" aria-label="{{ __st('souqify.home.promotions') }}">
        <div class="sq-hwrap">
            <div class="swiper !overflow-visible lg:!overflow-hidden" data-rail='{"spaceBetween":36}'>
                <div class="swiper-wrapper">
                    @foreach (array_slice($banners, 0, 4) as $banner)
                        <div class="swiper-slide !h-auto !w-[320px] lg:!w-[calc(50%-18px)]" data-reveal="fade-up">
                            <x-sf::souqify.banner :banner="$banner" size="promo" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
