{{--
    Souqify home, all six variants (Figma "Souqify Variants": v1 1:72835 / m 1:77852, v2 1:81585,
    v3 1:82288, v4 1:82796, v5 1:83157, v6 1:83936). The section list of the store's variant
    comes from SouqifyVariants (config, like EloraVariants — decision #79), arranged / re-sourced by
    the store's page builder (VEN-032); colours from the
    variant tokens. Every section hides itself when it has no data.
--}}
@php
    use App\Modules\Storefront\Support\SouqifyVariants;

    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $variant = $variant ?? 1;
    $rails = [
        'trending' => $trending ?? [],
        'best' => $best->cards,
        'newRail' => $newRail ?? [],
        'topRated' => $topRated ?? [],
        'offers' => $offers ?? [],
        'deals' => $deals,
    ];
    $sideBanners = $variant <= 1 ? array_slice($promo, 0, 2) : [];
    $promoBanners = $variant <= 1 ? array_slice($promo, 2) : [];
    $empty = $hero === [] && $deals === [] && $best->isEmpty() && ($newRail ?? []) === [] && ($categories ?? []) === [];
@endphp
<x-sf::layout :seo="$seo" nav="home" page="home" class="sq-home">
    <h1 class="sr-only">{{ $store }}</h1>

    @if ($empty)
        <section class="mx-auto grid max-w-[1440px] justify-items-center gap-4 px-4 py-24 text-center">
            <x-ui::icon name="store" :size="48" class="text-sf-muted" />
            <h2 class="text-xl font-medium">{{ __st('catalog.home.empty_title') }}</h2>
            <p class="max-w-md text-sm text-sf-muted">{{ __st('catalog.home.empty_body') }}</p>
        </section>
    @else
        @foreach ($homeSections ?? SouqifyVariants::sections($variant) as $section)
            @switch($section['type'])
                @case('hero')
                    <x-sf::souqify.section.hero :banners="$section['banners'] ?? $hero" :side="$sideBanners" :style="$section['style']" />
                    @break
                @case('features')
                    <x-sf::souqify.section.features :style="$section['style']" />
                    @break
                @case('categories')
                    <x-sf::souqify.section.categories :categories="$categories" :style="$section['style']" />
                    @break
                @case('flash')
                    <x-sf::souqify.section.flash :sale="$flash" :deals="$deals" :style="$section['style']" />
                    @break
                @case('rail')
                    <x-sf::souqify.section.rail :cards="$section['cards'] ?? $rails[$section['data']]" :section="$section" />
                    @break
                @case('wide')
                    <x-sf::souqify.section.wide :cards="$section['cards'] ?? $rails[$section['data']]" :section="$section" />
                    @break
                @case('promos')
                    <x-sf::souqify.section.promos :banners="$section['banners'] ?? $promoBanners" />
                    @break
                @case('side')
                    <x-sf::souqify.section.side :cards="$section['cards'] ?? $rails[$section['data']]" :section="$section" />
                    @break
                @case('featured')
                    <x-sf::souqify.section.featured :cards="$section['cards'] ?? $rails[$section['data']]" :offers="$rails['offers']" :section="$section" />
                    @break
                @case('shop-categories')
                    <x-sf::souqify.section.shop-categories :categories="$categories" :style="$section['style']" />
                    @break
                @case('best')
                    <x-sf::souqify.section.best :cards="$section['cards'] ?? $rails[$section['data']]" :section="$section" />
                    @break
                @case('badges')
                    @foreach ($badges as $shelf)
                        <x-sf::souqify.section.rail :cards="$shelf['cards']" :section="['title_text' => $shelf['name'], 'more' => null]" />
                    @endforeach
                    @break
                @case('recommended')
                    <x-sf::souqify.section.recommended :rows="$recommended" :style="$section['style']" />
                    @break
            @endswitch
        @endforeach
    @endif
</x-sf::layout>
