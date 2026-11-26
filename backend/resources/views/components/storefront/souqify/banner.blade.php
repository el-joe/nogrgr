{{--
    One Souqify promo / hero banner tile (BannerData): image (mobile source when set), dark
    gradient scrim, eyebrow · title · subtitle · CTA over it. size: side (v1 hero side cards) |
    promo (v1 "New Technology" pair).
--}}
@props(['banner', 'size' => 'promo', 'index' => 0])
@php($tag = $banner->url ? 'a' : 'div')
<{{ $tag }} @if ($banner->url) href="{{ $banner->url }}" @endif {{ $attributes->class(['sq-banner group', 'sq-banner-'.$size]) }}>
    <picture class="absolute inset-0" data-parallax="0.05">
        @if ($banner->mobileImageUrl)<source media="(max-width: 767px)" srcset="{{ $banner->mobileImageUrl }}">@endif
        <img src="{{ $banner->imageUrl }}" alt="{{ $banner->alt ?? $banner->title ?? '' }}" width="{{ $size === 'side' ? 466 : 670 }}" height="{{ $size === 'side' ? 288 : 309 }}" class="size-full object-cover transition duration-700 group-hover:scale-105" loading="lazy" decoding="async">
    </picture>
    @if ($banner->title || $banner->ctaLabel)
        <span class="sq-banner-copy">
            @if ($size === 'promo' && $banner->subtitle)<span class="text-xl max-lg:text-sm">{{ $banner->subtitle }}</span>@endif
            @if ($banner->title)<span class="sq-banner-title">{{ $banner->title }}</span>@endif
            @if ($size === 'side' && $banner->subtitle)<span class="text-xs font-medium uppercase tracking-[0.5px]">{{ $banner->subtitle }}</span>@endif
            @if ($size === 'promo' && $banner->ctaLabel)<span class="sq-banner-cta mo-press">{{ $banner->ctaLabel }}</span>@endif
        </span>
    @endif
</{{ $tag }}>
