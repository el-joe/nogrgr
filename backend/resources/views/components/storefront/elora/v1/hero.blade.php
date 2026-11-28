{{--
    Elora v1 hero (1:15548 desktop 1204 × 387, m 1:21126 329 × 106 with the next slide
    peeking): Swiper slides (autoplay, swipe / drag, keys, RTL) with the two round arrows of
    the design. Images keep their whole content at the 1204 × 387 ratio (2408 × 774 art).
    The pause button (WCAG 2.2.2) shows on keyboard focus / hover only.
--}}
@props(['banners' => []])
@if ($banners !== [])
    @php
        $many = count($banners) > 1;
        $options = ['delay' => 6000, 'effect' => 'slide', 'swiper' => ['slidesPerView' => 'auto', 'spaceBetween' => 15, 'breakpoints' => ['1024' => ['spaceBetween' => 32]]]];
    @endphp
    <section {{ $attributes->class(['v1-hero']) }} aria-roledescription="carousel" aria-label="{{ __st('catalog.home.hero_label') }}">
        <div class="v1-hero-frame">
            <div class="swiper mo-hero v1-hero-swiper" @if ($many) data-hero="{{ json_encode($options) }}" @endif>
                <div class="swiper-wrapper">
                    @foreach ($banners as $index => $banner)
                        @php($tag = $banner->url ? 'a' : 'div')
                        <div class="swiper-slide v1-hero-slide" role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} / {{ count($banners) }}">
                            <{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif class="v1-hero-link">
                                <picture class="block size-full">
                                    @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                                    <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" width="{{ $banner->width ?? 2408 }}" height="{{ $banner->height ?? 774 }}" class="h-full w-full object-contain" @if ($index === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif decoding="async">
                                </picture>
                                @if ($banner->title || $banner->ctaLabel)<span class="sr-only">{{ $banner->title }} {{ $banner->subtitle }} {{ $banner->ctaLabel }}</span>@endif
                            </{{ $tag }}>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($many)
                <button type="button" class="v1-arrow v1-arrow-prev" data-motion-prev aria-label="{{ __st('elora.home.previous') }}"><span class="v1-ico v1-ico-arrow" aria-hidden="true"></span></button>
                <button type="button" class="v1-arrow v1-arrow-next" data-motion-next aria-label="{{ __st('elora.home.next') }}"><span class="v1-ico v1-ico-arrow" aria-hidden="true"></span></button>
                <button type="button" class="v1-pause" data-motion-pause aria-label="{{ __st('catalog.home.pause') }}" data-label-play="{{ __st('catalog.home.play') }}" data-label-pause="{{ __st('catalog.home.pause') }}"><x-ui::icon name="minus" :size="12" class="rotate-90" /></button>
            @endif
        </div>
    </section>
@endif
