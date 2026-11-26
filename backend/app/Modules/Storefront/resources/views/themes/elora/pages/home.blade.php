{{--
    Elora home, all six variants (Figma "Elora Variants": v1 1:15432 / m 1:21125, v2 1:26559,
    v3 1:26978, v4 1:27464, v5 1:28050, v6 1:28992). The section list of the store's variant
    comes from EloraVariants (config, decision #79); colours from the variant tokens. Every
    section hides itself when it has no data.
--}}
@php
    use App\Modules\Storefront\Support\EloraVariants;

    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $variant = $variant ?? 1;
    $rails = [
        'trending' => $trending ?? [],
        'best' => $best->cards,
        'newRail' => $newRail ?? [],
        'topRated' => $topRated ?? [],
        'offers' => $offers ?? [],
        'deals' => $deals,
        'recommended' => $recommended[0] ?? [],
    ];
    $empty = $hero === [] && $deals === [] && $best->isEmpty() && ($newRail ?? []) === [] && ($categories ?? []) === [];
@endphp
<x-sf::layout :seo="$seo" nav="home" page="home" class="elo-home bg-[var(--elo-page)]">
    <h1 class="sr-only">{{ $store }}</h1>

    @if ($empty)
        <section class="mx-auto grid max-w-[1440px] justify-items-center gap-4 px-4 py-24 text-center">
            <x-ui::icon name="store" :size="48" class="text-sf-muted" />
            <h2 class="text-xl font-medium">{{ __st('catalog.home.empty_title') }}</h2>
            <p class="max-w-md text-sm text-sf-muted">{{ __st('catalog.home.empty_body') }}</p>
        </section>
    @else
        @foreach (EloraVariants::sections($variant) as $section)
            @switch($section['type'])
                @case('hero')
                    <x-sf::elora.section.hero :banners="$hero" :style="$section['style']" />
                    @break
                @case('features')
                    <x-sf::elora.section.features :style="$section['style']" :store="$store" />
                    @break
                @case('tabs')
                    <x-sf::elora.section.tabs :rails="$rails" />
                    @break
                @case('categories')
                    <x-sf::elora.section.categories :categories="$categories" :style="$section['style']" />
                    @break
                @case('flash')
                    <x-sf::elora.section.flash :sale="$flash" :deals="$deals" :promo="$promo" :style="$section['style']" />
                    @break
                @case('rail')
                    <x-sf::elora.section.rail :cards="$rails[$section['data']]" :section="$section" />
                    @break
                @case('wide')
                    <x-sf::elora.section.wide :cards="$rails[$section['data']]" :section="$section" />
                    @break
                @case('spotlight')
                    <x-sf::elora.section.spotlight :cards="$rails[$section['data']]" :section="$section" />
                    @break
                @case('category-band')
                    <x-sf::elora.section.category-band :categories="$categories" />
                    @break
                @case('shop-categories')
                    <x-sf::elora.section.shop-categories :categories="$categories" :style="$section['style']" />
                    @break
                @case('badges')
                    @foreach ($badges as $shelf)
                        <x-sf::elora.section.rail :cards="$shelf['cards']" :section="['title_text' => $shelf['name'], 'heading' => $variant === 1 ? 'split' : 'start', 'icon' => 'tag', 'more' => null]" />
                    @endforeach
                    @break
                @case('promo-rail')
                    <x-sf::elora.section.promo-rail :cards="$rails['offers'] !== [] ? $rails['offers'] : $rails['topRated']" />
                    @break
                @case('recommended')
                    <x-sf::elora.section.recommended :rows="$recommended" />
                    @break
            @endswitch
        @endforeach
    @endif
</x-sf::layout>
