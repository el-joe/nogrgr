{{--
    Hero banner slider (Ecommet 1:1004, 1440 × 279; m 375 × 73). Banners come from the
    merged BannerCatalog ('home.hero'); one banner = static, several = Swiper hero (fade,
    Ken Burns, autoplay with progress + pause, swipe / drag, RTL). Hidden when empty.
--}}
@props(['banners' => [], 'class' => ''])
@if ($banners !== [])
    <section {{ $attributes->class(['relative']) }} aria-roledescription="carousel" aria-label="{{ __st('catalog.home.hero_label') }}">
        <div class="swiper mo-hero" @if (count($banners) > 1) data-hero='{"delay":6000}' @endif>
            <div class="swiper-wrapper">
                @foreach ($banners as $index => $banner)
                    <div class="swiper-slide" role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} / {{ count($banners) }}">
                        @php($tag = $banner->url ? 'a' : 'div')
                        <{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif class="block aspect-[375/73] w-full overflow-hidden bg-eco-img lg:aspect-[1440/279]">
                            <picture>
                                @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                                <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" width="{{ $banner->width ?? 1440 }}" height="{{ $banner->height ?? 279 }}" class="mo-kenburns size-full object-cover" @if ($index === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif decoding="async">
                            </picture>
                            @if ($banner->title || $banner->ctaLabel)
                                <span class="sr-only">{{ $banner->title }} {{ $banner->subtitle }} {{ $banner->ctaLabel }}</span>
                            @endif
                        </{{ $tag }}>
                    </div>
                @endforeach
            </div>
            @if (count($banners) > 1)
                <div class="absolute inset-x-0 bottom-3 z-10 flex items-center justify-center gap-3">
                    <div class="swiper-pagination !static !w-auto" data-motion-pagination></div>
                    <button type="button" class="grid size-7 place-items-center rounded-full bg-black/40 text-white" data-motion-pause aria-label="{{ __st('catalog.home.pause') }}" data-label-play="{{ __st('catalog.home.play') }}" data-label-pause="{{ __st('catalog.home.pause') }}"><x-ui::icon name="minus" :size="14" class="rotate-90" /></button>
                </div>
                <span class="absolute inset-x-0 bottom-0 z-10 h-0.5 origin-left bg-white/80 rtl:origin-right" data-hero-progress></span>
            @endif
        </div>
    </section>
@endif
