{{-- Elora v1 "Shop by Category" (1:15770, desktop): orange band, white-footed photo cards. --}}
@props(['tiles' => []])
@php($items = array_values(array_filter($tiles, static fn (array $tile): bool => $tile['image'] !== null)))
@if ($items !== [])
    <section {{ $attributes->class(['v1-shop v1-grad-bg']) }} aria-labelledby="v1-shop-title">
        <h2 id="v1-shop-title" class="v1-shop-title" data-reveal="fade-up"><span class="v1-ico v1-ico-hand" aria-hidden="true"></span>{{ __st('elora.home.shop_by_category') }}</h2>
        <div class="v1-shop-rail">
            {{-- Starts centred on the middle tile: the ends are cut equally by the page edges. --}}
            <div class="swiper" data-rail="{{ json_encode(['spaceBetween' => 24, 'centeredSlides' => true, 'initialSlide' => intdiv(count($items), 2)]) }}">
                <div class="swiper-wrapper">
                    @foreach ($items as $tile)
                        <div class="swiper-slide v1-shop-slide">
                            <a href="{{ route('storefront.category', $tile['category']->slug) }}" class="v1-shop-card">
                                <span class="v1-shop-img"><img src="{{ $tile['image'] }}" alt="" width="231" height="273" loading="lazy" decoding="async"></span>
                                <span class="v1-shop-name">{{ $tile['category']->name }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
