{{--
    Promotion banner (old Ecommet home "promo_banner", after Best-Selling): the merged
    BannerCatalog 'home.promo' banners (or the ones picked in the page builder). A rounded
    dark card: image (mobile image on phones), title, subtitle and a CTA pill over it; the
    whole card links to the banner URL. Several banners swipe (touch + mouse drag). Hidden
    when there is no promo banner.
--}}
@props(['banners' => []])
@php
    $banners = array_values(array_filter($banners, static fn ($banner): bool => $banner->imageUrl || $banner->mobileImageUrl || $banner->title));
@endphp
@if ($banners !== [])
    <section {{ $attributes->class(['eco-container']) }} aria-label="{{ __st('catalog.home.promo_label') }}" data-reveal="fade-up">
        <div class="swiper" @if (count($banners) > 1) data-rail='{"spaceBetween":16,"slidesPerView":1}' @endif>
            <div class="swiper-wrapper">
                @foreach ($banners as $banner)
                    @php($tag = $banner->url ? 'a' : 'div')
                    <div class="swiper-slide">
                        <{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif class="eco-promo">
                            @if ($banner->imageUrl || $banner->mobileImageUrl)
                                <picture>
                                    @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                                    <img src="{{ $banner->imageUrl ?: $banner->mobileImageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" width="{{ $banner->width ?? 1280 }}" height="{{ $banner->height ?? 280 }}" loading="lazy" decoding="async">
                                </picture>
                            @endif
                            @if ($banner->title || $banner->subtitle || $banner->url)
                                <span class="absolute inset-0 flex flex-col justify-center gap-2 px-6 lg:px-10">
                                    @if ($banner->title)<span class="text-2xl font-bold text-white lg:text-3xl">{{ $banner->title }}</span>@endif
                                    @if ($banner->subtitle)<span class="mb-3 text-sm text-white/80 lg:text-base">{{ $banner->subtitle }}</span>@endif
                                    @if ($banner->url)<span class="eco-promo-cta">{{ $banner->ctaLabel ?: __st('catalog.home.promo_cta') }}</span>@endif
                                </span>
                            @endif
                        </{{ $tag }}>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
