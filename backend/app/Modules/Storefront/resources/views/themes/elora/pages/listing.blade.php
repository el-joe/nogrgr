{{--
    Elora product listing, shared by every Elora variant (Essence base design, decision #79) —
    one template, six entry points (Figma 1:17172 / m 1:21453,
    variant notes 137:113975; filter sheet m 1:22799, sort popup m 1:23024): categories,
    search results, best selling (period pills), new in, top rated and offers.
    Desktop: filter sidebar (applies on change) · quick-filter pills + sort · category
    circles rail · 5-up grid that loads the next page on scroll. Phones: search pill,
    swipeable quick pills, 2-up grid, floating "filter | sort by". Every filter is a query
    parameter, so the state lives in the URL.
    Data as the Ecommet listing (RendersListings) plus $tiles and $deliveryBy.
    Styles: resources/css/storefront/elora-listing.css; JS: storefront/elora/pages/listing.js.
--}}
@use('App\Modules\Storefront\Enums\ListingKind')
@use('App\Modules\Storefront\Enums\ListingSort')
@use('App\Modules\Storefront\Enums\SalesPeriod')
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingQuery $query */
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingResult $result */
    $isCategory = $kind === ListingKind::Category;
    $sortOptions = $kind->sorts();
    $filtersActive = $query->activeFilters();
    $popular ??= [];
    $tiles ??= [];
    $sortLabel = static fn (ListingSort $sort): string => $sort === ListingSort::Recommended ? __st('elora_pages.listing.most_relevant') : __st('catalog.sort.'.$sort->value);
    $sortUrl = static fn (ListingSort $sort): string => request()->fullUrlWithQuery(['sort' => $sort === $kind->defaultSort() ? null : $sort->value, 'page' => null, 'fragment' => null]);
    $toggle = static fn (string $name, int $value, bool $on): string => request()->fullUrlWithQuery([$name => $on ? null : $value, 'page' => null, 'fragment' => null]);
    /* Quick filters (1:17280 …): toggles of the current listing; the page that already is that list links back to all products. */
    $quick = [
        ['key' => 'flash', 'icon' => 'zap', 'param' => 'flash', 'value' => 1, 'on' => $query->flashOnly, 'is' => false],
        ['key' => 'top_rated', 'icon' => 'star', 'param' => 'rating', 'value' => 4, 'on' => $query->minRating !== null && $query->minRating >= 4, 'is' => $kind === ListingKind::TopRated],
        ['key' => 'fast', 'icon' => 'truck', 'param' => 'in_stock', 'value' => 1, 'on' => $query->inStockOnly, 'is' => false],
        ['key' => 'coupon', 'icon' => 'ticket', 'param' => 'sale', 'value' => 1, 'on' => $query->onSale, 'is' => $kind === ListingKind::Offers],
        ['key' => 'new_in', 'icon' => 'sparkles', 'param' => 'added', 'value' => 30, 'on' => $query->addedWithinDays !== null, 'is' => $kind === ListingKind::NewIn],
    ];
    $clearUrl = url()->current().($query->term ? '?q='.urlencode($query->term) : '');
    $imageSearch = \App\Modules\Ai\Services\Tools\OpenAiTools::storefrontImageSearch();
    /* Back goes one level up the category tree (not through the history), ending at /category. */
    $category ??= null;
    $parentCategory ??= null;
    $upUrl = $category === null ? null : ($parentCategory !== null ? route('storefront.category', ['slug' => $parentCategory->slug]) : route('storefront.category.all'));
    $upName = $category === null ? null : ($parentCategory !== null ? (string) $parentCategory->name : __st('elora_pages.listing.all_categories'));
    $back = $upUrl ?? ($isCategory || url()->previous() === url()->current() ? route('storefront.home') : url()->previous());
@endphp
<x-sf::layout :seo="$seo" :page="'listing-'.$kind->value" :nav="$isCategory ? 'categories' : null">
    {{-- Phone search pill (m 1:21528): back, the listing name as the hint, image search. --}}
    <form method="GET" action="{{ route('storefront.search') }}" role="search" class="elp-search lg:hidden">
        <a href="{{ $back }}" class="elp-search-back" data-elp-back aria-label="{{ $upName !== null ? __st('elora_pages.listing.back_to', ['name' => $upName]) : __st('shell.back') }}"><span class="elp-ico elp-ico-back" aria-hidden="true"></span></a>
        <label for="elp-m-search" class="sr-only">{{ __st('shell.search_submit') }}</label>
        <input id="elp-m-search" type="search" name="q" value="{{ $query->term }}" placeholder="{{ $isCategory ? $title : __st('shell.search') }}" enterkeyhint="search" autocomplete="off" required>
        <button type="submit" class="sr-only">{{ __st('shell.search_submit') }}</button>
        @if ($imageSearch)
            <button type="button" class="elp-search-camera" data-image-search-open aria-label="{{ __st('search.image.open') }}"><span class="elp-ico elp-ico-camera" aria-hidden="true"></span></button>
        @endif
    </form>

    <div class="elp-wrap">
        <aside class="elp-side" aria-label="{{ __st('catalog.filters.title') }}">
            @include('storefront::themes.elora.partials.filters', ['mode' => 'sidebar'])
        </aside>

        <div class="elp-main">
            {{-- Not drawn in the design; kept for readers and search engines (JSON-LD carries the trail too). --}}
            <nav aria-label="{{ __st('shell.breadcrumb') }}" class="sr-only">
                <ol>
                    @foreach ($crumbs as $crumb)
                        <li>@if (! $loop->last)<a href="{{ $crumb['url'] }}" tabindex="-1">{{ $crumb['name'] }}</a>@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif</li>
                    @endforeach
                </ol>
            </nav>
            <h1 class="sr-only">{{ $title }} — {{ __st('elora_pages.listing.results', ['count' => $result->total]) }}</h1>

            {{-- Quick filters + sort (1:17278 · m 1:21536) --}}
            <div class="elp-toolbar">
                <div class="swiper elp-pills" data-rail='{"spaceBetween":12,"freeMode":{"enabled":true,"sticky":false}}'>
                    <ul class="swiper-wrapper" role="list" aria-label="{{ __st('elora_pages.listing.quick_label') }}">
                        @foreach ($quick as $pill)
                            <li class="swiper-slide !w-auto">
                                <a href="{{ $pill['is'] ? route('storefront.category.all') : $toggle($pill['param'], $pill['value'], $pill['on']) }}" class="elp-pill mo-press" data-elp-nav @if ($pill['on'] || $pill['is']) aria-current="true" @endif>
                                    <span class="elp-ico elp-ico-{{ $pill['icon'] }}" aria-hidden="true"></span>
                                    @if ($pill['key'] === 'top_rated')
                                        <span class="lg:hidden">{{ __st('elora_pages.listing.quick.top_rated_short') }}</span><span class="max-lg:hidden">{{ __st('elora_pages.listing.quick.top_rated') }}</span>
                                    @else
                                        <span>{{ __st('elora_pages.listing.quick.'.$pill['key']) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                {{-- Select2 (elora/pages/listing-sort.js) styled as the drawn pill; the native select is the no-JS fallback. --}}
                <div class="elp-sort">
                    <label class="sr-only" for="elp-sort">{{ __st('catalog.sort.label') }}</label>
                    <select id="elp-sort" data-auto-filter="sort" data-elp-sort data-label="{{ __st('catalog.sort.label') }}">
                        @foreach ($sortOptions as $sort)
                            <option value="{{ $sort->value }}" @selected($query->sort === $sort)>{{ $sortLabel($sort) }}</option>
                        @endforeach
                    </select>
                    <span class="elp-ico elp-ico-sort" aria-hidden="true"></span>
                </div>
            </div>

            @if ($kind === ListingKind::BestSelling)
                <div class="elp-periods" role="group" aria-label="{{ __st('catalog.listing.best_selling.period') }}">
                    @foreach (SalesPeriod::cases() as $period)
                        <a href="{{ request()->fullUrlWithQuery(['period' => $period->value, 'page' => null, 'fragment' => null]) }}" class="elo-pill elo-pill-ghost mo-press" data-elp-nav @if ($query->period === $period) aria-current="true" @endif>{{ __st('catalog.listing.best_selling.within', ['days' => $period->value]) }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Category circles (1:17317), desktop; on a category page the "up one level" tile stays put before the rail. --}}
            @if ($tiles !== [] || $upUrl !== null)
                <div class="elp-circles" data-reveal="fade">
                    @if ($upUrl !== null)
                        <a href="{{ $upUrl }}" class="elp-circle elp-up mo-press" rel="up" data-elp-back title="{{ __st('elora_pages.listing.back_to', ['name' => $upName]) }}" aria-label="{{ __st('elora_pages.listing.back_to', ['name' => $upName]) }}">
                            <span class="elp-circle-img"><span class="elp-ico elp-ico-up" aria-hidden="true"></span></span>
                            <span class="elp-circle-name">{{ $upName }}</span>
                        </a>
                    @endif
                    @if ($tiles !== [])
                        <div class="swiper elp-circle-rail" data-rail='{"spaceBetween":24,"freeMode":{"enabled":true,"sticky":false}}'>
                            <ul class="swiper-wrapper" role="list" aria-label="{{ __st('shell.nav.categories') }}">
                                @foreach ($tiles as $tile)
                                    <li class="swiper-slide !w-20">
                                        <a href="{{ route('storefront.category', ['slug' => $tile['category']->slug]) }}" class="elp-circle" @if ($isCategory && $tile['category']->id === $category?->id) aria-current="page" @endif>
                                            <span class="elp-circle-img">
                                                @if ($tile['image'])
                                                    <img src="{{ $tile['image'] }}" alt="" width="62" height="62" loading="{{ $loop->index < 11 ? 'eager' : 'lazy' }}" decoding="async" draggable="false">
                                                @else
                                                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $tile['category']->name, 0, 1)) }}</span>
                                                @endif
                                            </span>
                                            <span class="elp-circle-name">{{ $tile['category']->name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            @if ($result->isEmpty())
                <section class="elo-empty" data-reveal="fade-up">
                    <span class="elo-empty-icon"><x-ui::icon :name="$kind === ListingKind::Search ? 'search' : 'box'" :size="40" /></span>
                    <h2>{{ $filtersActive ? __st('catalog.empty.filtered_title') : __st('catalog.empty.'.$kind->value.'_title', ['term' => $query->term ?? '']) }}</h2>
                    <p class="max-w-md">{{ $filtersActive ? __st('catalog.empty.filtered_body') : __st('catalog.empty.'.$kind->value.'_body') }}</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-3">
                        @if ($filtersActive)
                            <a href="{{ $clearUrl }}" class="elo-btn elo-btn-ink mo-press">{{ __st('catalog.filters.reset') }}</a>
                        @elseif ($kind === ListingKind::BestSelling || $kind === ListingKind::TopRated)
                            <a href="{{ route('storefront.new-in') }}" class="elo-btn elo-btn-ink mo-press">{{ __st('catalog.empty.show_new') }}</a>
                        @else
                            <a href="{{ route('storefront.new-in') }}" class="elo-btn elo-btn-ink mo-press">{{ __st('catalog.empty.browse_all') }}</a>
                        @endif
                    </div>
                    @if ($kind === ListingKind::Offers && ! $filtersActive && Route::has('storefront.newsletter.store'))
                        <div class="mt-6 w-full max-w-3xl text-start">@include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.newsletter-signup'))</div>
                    @endif
                </section>
                @if ($popular !== [])
                    <section class="grid gap-4" aria-labelledby="elp-popular-title">
                        <h2 id="elp-popular-title" class="text-lg font-medium text-elo-ink" data-reveal="fade-up">{{ __st('search.popular') }}</h2>
                        <div class="elp-grid" data-reveal-stagger>
                            @include('storefront::themes.elora.partials.listing-cards', ['cards' => $popular])
                        </div>
                    </section>
                @endif
            @else
                <div id="elo-listing-grid" class="elp-grid" data-reveal-stagger>
                    @include('storefront::themes.elora.partials.listing-cards', ['cards' => $result->cards, 'eager' => $query->page === 1])
                </div>
                {{-- The next page loads as the end of the grid comes into view; the link stays for keyboards, crawlers and retries. --}}
                @if ($nextUrl)
                    <div class="elp-more">
                        <a href="{{ $nextUrl }}" class="elp-more-link" data-load-more data-elp-auto data-target="#elo-listing-grid" data-loading-text="{{ __st('catalog.loading') }}" data-error-text="{{ __st('catalog.load_error') }}" rel="next">
                            <span class="elp-spinner" aria-hidden="true"></span><span data-load-more-label>{{ __st('catalog.see_more') }}</span>
                        </a>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Phone floating "filter | sort by" (m 1:21569) --}}
    <div class="elp-fab lg:hidden">
        <div>
            <button type="button" data-sheet-open="elo-filters"><span class="elp-ico elp-ico-filter" aria-hidden="true"></span>{{ __st('elora_pages.listing.filter') }}@if ($filtersActive)<span class="elp-fab-count">{{ $filtersActive }}</span>@endif</button>
            <span class="elp-fab-line" aria-hidden="true"></span>
            <button type="button" data-sheet-open="elo-sort"><span class="elp-ico elp-ico-arrows" aria-hidden="true"></span>{{ __st('elora_pages.listing.sort_by') }}</button>
        </div>
    </div>

    {{-- Phone filters (m 1:22799) --}}
    <div class="mo-sheet elp-sheet" id="elo-filters" data-sheet data-sheet-mode="always" role="dialog" aria-modal="true" aria-label="{{ __st('catalog.filters.title') }}" hidden>
        <div class="mo-sheet-scrim" data-sheet-close></div>
        <div class="mo-sheet-panel">
            <div class="elp-sheet-head" data-sheet-handle data-autofocus tabindex="-1">
                <x-sf::logo tone="dark" size="lg" />
                <button type="button" data-sheet-close class="elp-sheet-close" aria-label="{{ __st('shell.close') }}"><span class="elp-ico elp-ico-close" aria-hidden="true"></span></button>
            </div>
            @include('storefront::themes.elora.partials.filters', ['mode' => 'sheet'])
        </div>
    </div>

    <x-sf::modal id="elo-sort" :title="__st('catalog.sort.label')" size="sm" data-sheet-mode="always">
        <ul class="grid" role="list">
            @foreach ($sortOptions as $sort)
                <li>
                    <a href="{{ $sortUrl($sort) }}" class="elo-sort-option" @if ($query->sort === $sort) aria-current="true" @endif>
                        {{ $sortLabel($sort) }}
                        <span class="elo-radio" aria-hidden="true">@if ($query->sort === $sort)<x-ui::icon name="check" :size="14" />@endif</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-sf::modal>
</x-sf::layout>
