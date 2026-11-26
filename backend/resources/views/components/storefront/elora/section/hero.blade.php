{{--
    Elora hero banners (merged BannerCatalog 'home.hero'). style="contained" (v1 1:15432:
    1200-wide rounded slide, round prev / next buttons outside) or "full" (v2–v6: full-width
    543 tall slide with the banner title / subtitle / CTA over it, pill dots under it).
    Swiper hero: autoplay + progress, Ken Burns, swipe / drag, keys, RTL; hidden when empty.
--}}
@props(['banners' => [], 'style' => 'full'])
@if ($banners !== [])
    @php($many = count($banners) > 1)
    <section @class(['elo-hero relative', 'elo-hero-contained' => $style === 'contained', 'elo-hero-full' => $style === 'full']) aria-roledescription="carousel" aria-label="{{ __st('catalog.home.hero_label') }}">
        <div class="swiper mo-hero elo-hero-swiper" @if ($many) data-hero='{"delay":6000}' @endif>
            <div class="swiper-wrapper">
                @foreach ($banners as $index => $banner)
                    @php($tag = $banner->url ? 'a' : 'div')
                    <div class="swiper-slide" role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} / {{ count($banners) }}">
                        <{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif class="elo-hero-slide">
                            <picture class="block size-full" data-parallax="0.06">
                                @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                                <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" width="{{ $banner->width ?? 1440 }}" height="{{ $banner->height ?? 543 }}" class="mo-kenburns size-full object-contain bg-media" @if ($index === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif decoding="async">
                            </picture>
                            @if ($style === 'full' && ($banner->title || $banner->ctaLabel))
                                <span class="elo-hero-copy">
                                    @if ($banner->title)<span class="elo-hero-title">{{ $banner->title }}</span>@endif
                                    @if ($banner->subtitle)<span class="elo-hero-sub">{{ $banner->subtitle }}</span>@endif
                                    @if ($banner->ctaLabel)<span class="elo-hero-cta mo-press">{{ $banner->ctaLabel }}</span>@endif
                                </span>
                            @elseif ($banner->title || $banner->ctaLabel)
                                <span class="sr-only">{{ $banner->title }} {{ $banner->subtitle }} {{ $banner->ctaLabel }}</span>
                            @endif
                        </{{ $tag }}>
                    </div>
                @endforeach
            </div>
            @if ($many)
                <span class="absolute inset-x-0 bottom-0 z-10 h-0.5 origin-left bg-white/80 rtl:origin-right" data-hero-progress></span>
            @endif
        </div>
        @if ($many)
            @if ($style === 'contained')
                <button type="button" class="elo-round-btn absolute -start-3 top-1/2 z-10 -translate-y-1/2 max-lg:hidden" data-motion-prev aria-label="{{ __st('elora.home.previous') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></button>
                <button type="button" class="elo-round-btn absolute -end-3 top-1/2 z-10 -translate-y-1/2 max-lg:hidden" data-motion-next aria-label="{{ __st('elora.home.next') }}"><x-ui::icon name="chevron-right" :size="20" class="rtl:-scale-x-100" /></button>
            @endif
            <div class="elo-hero-dots">
                <div class="swiper-pagination !static !w-auto" data-motion-pagination></div>
                <button type="button" class="grid size-6 place-items-center rounded-full text-sf-muted" data-motion-pause aria-label="{{ __st('catalog.home.pause') }}" data-label-play="{{ __st('catalog.home.play') }}" data-label-pause="{{ __st('catalog.home.pause') }}"><x-ui::icon name="minus" :size="12" class="rotate-90" /></button>
            </div>
        @endif
    </section>
@endif
