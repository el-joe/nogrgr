{{--
    Store-page section "Banner" (VEN-032): the banners the store picked (live in the shopping
    country), contained (card width) or full (edge to edge); several = swipeable slider
    (Swiper "rail": touch, mouse drag, keys, RTL). Images object-contain.
--}}
@props(['section'])
@php $full = $section['style'] === 'full'; @endphp
<section @class(['relative', '-mx-4 md:-mx-[120px]' => $full]) aria-label="{{ $section['title'] ?? __st('pages.sections.banner') }}" data-reveal="fade-up">
    <div class="swiper" data-rail='{"spaceBetween":16,"slidesPerView":1}'>
        <div class="swiper-wrapper">
            @foreach ($section['banners'] as $index => $banner)
                <div class="swiper-slide">
                    @php $tag = $banner->url ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif @class(['group relative block overflow-hidden bg-sf-bg', 'rounded-sf-card' => ! $full])>
                        <picture>
                            @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
                            <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" @if ($banner->width) width="{{ $banner->width }}" @endif @if ($banner->height) height="{{ $banner->height }}" @endif class="h-auto max-h-[520px] w-full object-contain transition-transform duration-500 motion-safe:group-hover:scale-[1.02]" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async">
                        </picture>
                        @if ($banner->title || $banner->ctaLabel)
                            <span class="absolute inset-x-0 bottom-0 flex flex-wrap items-end justify-between gap-3 bg-gradient-to-t from-black/60 to-transparent p-4 text-white md:p-8">
                                <span class="grid gap-1">
                                    @if ($banner->title)<span class="text-lg font-semibold md:text-2xl">{{ $banner->title }}</span>@endif
                                    @if ($banner->subtitle)<span class="text-sm opacity-90">{{ $banner->subtitle }}</span>@endif
                                </span>
                                @if ($banner->ctaLabel)<span class="rounded-sf-input bg-white px-4 py-2 text-sm font-medium text-sf-ink">{{ $banner->ctaLabel }}</span>@endif
                            </span>
                        @endif
                    </{{ $tag }}>
                </div>
            @endforeach
        </div>
    </div>
</section>
