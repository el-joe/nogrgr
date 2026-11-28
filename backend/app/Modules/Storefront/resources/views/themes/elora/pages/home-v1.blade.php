{{--
    Elora v1 (base) home — Figma "Elora Variants" 1:15432 desktop / m 1:21125 phone. Section
    list and order: EloraVariants v1 / the store's page builder. Phones follow the phone
    frame's own order (categories above the tabs, mini recommended band, buy together, grid)
    while the store keeps the theme order. Sections without data hide themselves.
--}}
@php
    use App\Modules\Storefront\Support\EloraVariants;

    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $sections = $homeSections ?? array_values(array_filter(EloraVariants::sections(1), static fn (array $section): bool => $section['enabled'] ?? true));
    $defaultKeys = ['hero', 'features', 'tabs', 'categories', 'flash', 'rail.trending', 'category-band', 'rail.best', 'rail.newRail', 'shop-categories', 'rail.recommended', 'promo-rail'];
    $keys = array_column($sections, 'key');
    /* Phone-only order of the phone frame: only while the sections are the theme's own order. */
    $phoneOrder = $keys === [] || $keys === array_values(array_intersect($defaultKeys, $keys));
    $recommendedCards = $recommended[0] ?? [];
    $gridCards = array_slice(array_merge(...($recommended ?: [[]])), 0, 20);
    $rails = [
        'trending' => $trending ?? [],
        'best' => $best->cards,
        'newRail' => $newRail ?? [],
        'topRated' => $topRated ?? [],
        'offers' => $offers ?? [],
        'deals' => $deals,
        'fast' => $fast ?? [],
        'recommended' => $recommendedCards,
    ];
    $delivery = $deliveryBy ?? null;
    $tiles = $tiles ?? [];
    $empty = $hero === [] && $deals === [] && $best->isEmpty() && ($newRail ?? []) === [] && $tiles === [];
@endphp
<x-sf::layout :seo="$seo" nav="home" page="home" class="elo-home v1-home bg-[var(--elo-page)]">
    <h1 class="sr-only">{{ $store }}</h1>

    @if ($empty)
        <section class="mx-auto grid max-w-[1440px] justify-items-center gap-4 px-4 py-24 text-center">
            <x-ui::icon name="store" :size="48" class="text-sf-muted" />
            <h2 class="text-xl font-medium">{{ __st('catalog.home.empty_title') }}</h2>
            <p class="max-w-md text-sm text-sf-muted">{{ __st('catalog.home.empty_body') }}</p>
        </section>
    @else
        <div @class(['v1-stack', 'v1-phone-order' => $phoneOrder])>
            @foreach ($sections as $section)
                @switch($section['type'])
                    @case('hero')
                        <x-sf::elora.v1.hero :banners="$section['banners'] ?? $hero" />
                        @break
                    @case('features')
                        <x-sf::elora.v1.features />
                        @break
                    @case('tabs')
                        <x-sf::elora.v1.tabs :rails="$rails" :delivery="$delivery" />
                        @break
                    @case('categories')
                        <x-sf::elora.v1.categories :tiles="$tiles" />
                        @break
                    @case('flash')
                        <x-sf::elora.v1.flash :sale="$flash" :deals="$deals" :code="$section['code_text'] ?? null" :delivery="$delivery" />
                        @break
                    @case('rail')
                        @if ($section['data'] === 'recommended')
                            <x-sf::elora.v1.product-section :cards="$section['cards'] ?? $recommendedCards" :section="['more' => null] + $section" :delivery="$delivery" class="v1-rec-rail max-lg:hidden" />
                            <x-sf::elora.v1.recommended-band :cards="$section['cards'] ?? array_slice($gridCards, 0, 10)" :title="$section['title_text'] ?? __st('elora.home.recommended_band')" class="v1-m-band lg:hidden" />
                            <x-sf::elora.v1.recommended-grid :cards="$section['cards'] ?? $gridCards" :title="$section['title_text'] ?? __st($section['title'])" :more="route('storefront.new-in')" :delivery="$delivery" class="v1-m-grid lg:hidden" />
                        @else
                            <x-sf::elora.v1.product-section :cards="$section['cards'] ?? $rails[$section['data']]" :section="$section" :delivery="$delivery" :class="'v1-rail-'.strtolower($section['data'])" />
                        @endif
                        @break
                    @case('category-band')
                        <x-sf::elora.v1.category-band :tiles="$tiles" class="max-lg:hidden" />
                        @break
                    @case('shop-categories')
                        <x-sf::elora.v1.shop-categories :tiles="$tiles" class="max-lg:hidden" />
                        @break
                    @case('badges')
                        @foreach ($badges as $shelf)
                            <x-sf::elora.v1.product-section :cards="$shelf['cards']" :section="['title_text' => $shelf['name'], 'data' => 'best', 'more' => null]" :delivery="$delivery" class="v1-badges" />
                        @endforeach
                        @break
                    @case('promo-rail')
                        @php($promoCards = $rails['offers'] !== [] ? $rails['offers'] : $rails['topRated'])
                        <x-sf::elora.v1.promo-rail :cards="$promoCards" :weight="$freeShippingWeight ?? null" :delivery="$delivery" class="max-lg:hidden" />
                        <x-sf::elora.v1.buy-together :cards="count($rails['offers']) >= 2 ? $rails['offers'] : $rails['best']" class="v1-m-together lg:hidden" />
                        @break
                @endswitch
            @endforeach
        </div>
    @endif
</x-sf::layout>
