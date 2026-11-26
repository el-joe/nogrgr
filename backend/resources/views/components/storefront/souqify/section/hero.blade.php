{{--
    Souqify hero (merged BannerCatalog 'home.hero' + 'home.promo').
      deck    v1 1:72836: rounded 813×600 slider at the start + two side banners (the first two
              promo banners) stacked at the end; phone: the slider only (m 1:77852).
      split   v2 / v4 / v5 / v6: full-width banner, copy at the start — the title's first word in
              the variant accent, big pill "Shop Now".
      center  v3: full-width dark banner, centred copy.
    Swiper hero: autoplay + progress bar, Ken Burns, swipe / drag, keys, pause control, RTL;
    hidden when the store has no hero banners.
--}}
@props(['banners' => [], 'side' => [], 'style' => 'deck'])
@if ($banners !== [])
    @php $many = count($banners) > 1; @endphp
    <section @class(['sq-hero', 'sq-hero-'.$style]) aria-roledescription="carousel" aria-label="{{ __st('catalog.home.hero_label') }}">
        <div @class(['sq-hwrap grid gap-6 lg:grid-cols-[minmax(0,813fr)_minmax(0,466fr)]' => $style === 'deck' && $side !== [], 'sq-hwrap' => $style === 'deck' && $side === []])>
            <div class="sq-hero-main relative min-w-0" data-reveal="fade">
                <div class="swiper mo-hero sq-hero-swiper" @if ($many) data-hero='{"delay":6000}' @endif>
                    <div class="swiper-wrapper">
                        @foreach ($banners as $index => $banner)
                            @php
                                $tag = $banner->url ? 'a' : 'div';
                                $words = $banner->title ? preg_split('/\s+/u', trim($banner->title), 2) : [];
                            @endphp
                            <div class="swiper-slide" role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} / {{ count($banners) }}">
                                <{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif class="sq-hero-slide">
                                    <picture class="block size-full" data-parallax="0.06">
                                        @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                                        <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" width="{{ $banner->width ?? ($style === 'deck' ? 813 : 1440) }}" height="{{ $banner->height ?? 600 }}" class="mo-kenburns size-full object-cover" @if ($index === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif decoding="async">
                                    </picture>
                                    @if ($banner->title || $banner->ctaLabel)
                                        <span class="sq-hero-copy">
                                            @if ($style === 'deck' && $banner->subtitle)<span class="sq-hero-chip">{{ $banner->subtitle }}</span>@endif
                                            @if ($words !== [])
                                                <span class="sq-hero-title"><span @class(['sq-hero-word' => $style !== 'deck'])>{{ $words[0] }}</span>{{ isset($words[1]) ? ' '.$words[1] : '' }}</span>
                                            @endif
                                            @if ($style !== 'deck' && $banner->subtitle)<span class="sq-hero-sub">{{ $banner->subtitle }}</span>@endif
                                            @if ($banner->ctaLabel)<span class="sq-hero-cta mo-press">{{ $banner->ctaLabel }}</span>@endif
                                        </span>
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
                    <div class="sq-hero-dots">
                        <div class="swiper-pagination !static !w-auto" data-motion-pagination></div>
                        <button type="button" class="grid size-6 place-items-center rounded-full bg-black/30 text-white" data-motion-pause aria-label="{{ __st('catalog.home.pause') }}" data-label-play="{{ __st('catalog.home.play') }}" data-label-pause="{{ __st('catalog.home.pause') }}"><x-ui::icon name="minus" :size="12" class="rotate-90" /></button>
                    </div>
                @endif
            </div>
            @if ($style === 'deck' && $side !== [])
                <div class="grid gap-6 max-lg:hidden" data-reveal-stagger>
                    @foreach (array_slice($side, 0, 2) as $banner)
                        <x-sf::souqify.banner :banner="$banner" size="side" data-reveal="slide-start" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
