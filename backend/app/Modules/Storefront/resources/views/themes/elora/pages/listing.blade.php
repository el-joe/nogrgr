{{--
    Elora product listing — one template, six entry points (Figma 1:17172 / m 1:21453,
    variant notes 137:113975; filter sheet m 1:22799, sort popup m 1:23024): search results,
    best selling (period pills), new in, top rated, offers and categories. Desktop: filter
    sidebar (applies on change) + quick-filter pills + sort; category circles rail; 5-up
    grid; load more. Phones: search pill, swipeable quick pills, floating "filter | sort by".
    Data as the Ecommet listing (RendersListings): $kind, $title, $crumbs, $query, $result,
    $nextUrl, $category, $subcategories, $categoryOptions, $popular, $cardTag, $seo.
--}}
@use('App\Modules\Storefront\Enums\ListingKind')
@use('App\Modules\Storefront\Enums\SalesPeriod')
@php
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingQuery $query */
    /** @var \App\Modules\Storefront\DTOs\Catalog\ListingResult $result */
    $isCategory = $kind === ListingKind::Category;
    $sortOptions = $kind->sorts();
    $filtersActive = $query->activeFilters();
    $subcategories ??= [];
    $popular ??= [];
    $circles = $isCategory
        ? $subcategories
        : array_map(static fn (array $node) => $node['category'], app(\App\Modules\Storefront\Services\Catalog\CategoryCatalog::class)->tree());
    $quick = [
        ['route' => 'storefront.offers', 'params' => ['flash' => 1], 'icon' => 'zap', 'label' => 'elora_pages.listing.quick.flash', 'active' => $kind === ListingKind::Offers && $query->flashOnly],
        ['route' => 'storefront.top-rated', 'params' => [], 'icon' => 'star', 'label' => 'elora_pages.listing.quick.top_rated', 'active' => $kind === ListingKind::TopRated],
        ['route' => 'storefront.best-sellers', 'params' => [], 'icon' => 'award', 'label' => 'elora_pages.listing.quick.best_selling', 'active' => $kind === ListingKind::BestSelling],
        ['route' => 'storefront.offers', 'params' => [], 'icon' => 'tag', 'label' => 'elora_pages.listing.quick.offers', 'active' => $kind === ListingKind::Offers && ! $query->flashOnly],
        ['route' => 'storefront.new-in', 'params' => [], 'icon' => 'sparkles', 'label' => 'elora_pages.listing.quick.new_in', 'active' => $kind === ListingKind::NewIn],
    ];
    $sortUrl = fn ($sort) => request()->fullUrlWithQuery(['sort' => $sort->value === $kind->defaultSort()->value ? null : $sort->value, 'page' => null, 'fragment' => null]);
@endphp
<x-sf::layout :seo="$seo" :page="'listing-'.$kind->value" :nav="$isCategory ? 'categories' : null">
    {{-- Phone search pill (m 1:21453) --}}
    <div class="elo-wrap pt-3 lg:hidden">
        <form method="GET" action="{{ route('storefront.search') }}" role="search" class="flex h-12 items-center gap-3 rounded-full bg-elo-soft px-4 shadow-[0_1px_3px_rgb(0_0_0/0.08)]">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('storefront.home') }}" class="text-elo-ink" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></a>
            <label for="elo-m-search" class="sr-only">{{ __st('shell.search_submit') }}</label>
            <input id="elo-m-search" type="search" name="q" value="{{ $query->term }}" placeholder="{{ __st('shell.search') }}" class="h-full min-w-0 flex-1 bg-transparent text-sm text-elo-ink outline-none" enterkeyhint="search" autocomplete="off">
            <button type="submit" class="text-elo-ink" aria-label="{{ __st('shell.search_submit') }}"><x-ui::icon name="search" :size="20" /></button>
        </form>
    </div>

    <div class="elo-wrap grid gap-6 pb-28 pt-4 lg:grid-cols-[260px_minmax(0,1fr)] lg:gap-8 lg:ps-0 lg:pb-16 lg:pt-6">
        {{-- Desktop filter sidebar --}}
        <aside class="elo-filters hidden self-start px-7 pb-8 pt-4 lg:block" aria-label="{{ __st('catalog.filters.title') }}" data-reveal="slide-start">
            @include('storefront::themes.elora.partials.filters', ['mode' => 'sidebar'])
        </aside>

        <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] content-start gap-5 lg:gap-6">
            @if ($isCategory || $kind === ListingKind::Search)
                <nav aria-label="{{ __st('shell.breadcrumb') }}" class="hidden lg:block">
                    <ol class="elo-crumbs">
                        @foreach ($crumbs as $crumb)
                            <li class="inline-flex items-center gap-1.5">
                                @if (! $loop->last)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" />@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            {{-- Quick filters + sort --}}
            <div class="flex items-center gap-4">
                <div class="swiper min-w-0 flex-1" data-rail='{"spaceBetween":12,"freeMode":{"enabled":true,"sticky":false}}'>
                    <ul class="swiper-wrapper" role="list" aria-label="{{ __st('elora_pages.listing.quick_label') }}">
                        @foreach ($quick as $pill)
                            <li class="swiper-slide !w-auto">
                                <a href="{{ route($pill['route'], $pill['params']) }}" class="elo-pill mo-press" @if ($pill['active']) aria-current="page" @endif><x-ui::icon :name="$pill['icon']" :size="16" />{{ __st($pill['label']) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <label class="elo-sort hidden shrink-0 lg:inline-flex">
                    <span class="sr-only">{{ __st('catalog.sort.label') }}</span>
                    <select data-auto-filter="sort" aria-label="{{ __st('catalog.sort.label') }}">
                        @foreach ($sortOptions as $sort)
                            <option value="{{ $sort->value }}" @selected($query->sort === $sort)>{{ __st('catalog.sort.'.$sort->value) }}</option>
                        @endforeach
                    </select>
                    <x-ui::icon name="sort" :size="16" class="pointer-events-none text-elo-primary" />
                </label>
            </div>

            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="text-lg font-medium text-elo-ink lg:text-xl">{{ $title }}</h1>
                @unless ($result->isEmpty())
                    <p class="text-sm text-sf-muted">{{ __st('elora_pages.listing.results', ['count' => $result->total]) }}</p>
                @endunless
                @if ($filtersActive)
                    <a href="{{ url()->current().($query->term ? '?q='.urlencode($query->term) : '') }}" class="ms-auto inline-flex items-center gap-1 text-sm text-elo-primary hover:underline lg:hidden"><x-ui::icon name="x" :size="14" />{{ __st('elora_pages.listing.clear_all') }} ({{ $filtersActive }})</a>
                @endif
            </div>

            @if ($kind === ListingKind::BestSelling)
                <div class="flex gap-2 overflow-x-auto [scrollbar-width:none]" role="group" aria-label="{{ __st('catalog.listing.best_selling.period') }}">
                    @foreach (SalesPeriod::cases() as $period)
                        <a href="{{ request()->fullUrlWithQuery(['period' => $period->value, 'page' => null, 'fragment' => null]) }}" class="elo-pill elo-pill-ghost mo-press" @if ($query->period === $period) aria-current="true" @endif>{{ __st('catalog.listing.best_selling.within', ['days' => $period->value]) }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Category circles --}}
            @if ($circles !== [])
                <div class="swiper w-full" data-rail='{"spaceBetween":10}' data-reveal="fade">
                    <ul class="swiper-wrapper" role="list" aria-label="{{ __st('shell.nav.categories') }}">
                        @foreach ($circles as $circle)
                            <li class="swiper-slide !w-[82px]">
                                <a href="{{ route('storefront.category', ['slug' => $circle->slug]) }}" class="elo-cat" @if ($isCategory && $circle->id === $category?->id) aria-current="page" @endif>
                                    <x-sf::category-image :category="$circle" :size="64" :eager="$loop->index < 8" />
                                    <span class="line-clamp-2">{{ $circle->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($result->isEmpty())
                <section class="elo-empty" data-reveal="fade-up">
                    <span class="elo-empty-icon"><x-ui::icon :name="$kind === ListingKind::Search ? 'search' : 'box'" :size="40" /></span>
                    <h2>{{ $filtersActive ? __st('catalog.empty.filtered_title') : __st('catalog.empty.'.$kind->value.'_title', ['term' => $query->term ?? '']) }}</h2>
                    <p class="max-w-md">{{ $filtersActive ? __st('catalog.empty.filtered_body') : __st('catalog.empty.'.$kind->value.'_body') }}</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-3">
                        @if ($filtersActive)
                            <a href="{{ url()->current().($query->term ? '?q='.urlencode($query->term) : '') }}" class="elo-btn elo-btn-ink mo-press">{{ __st('catalog.filters.reset') }}</a>
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
                    <section class="grid gap-4" aria-labelledby="elo-popular-title">
                        <h2 id="elo-popular-title" class="text-lg font-medium text-elo-ink" data-reveal="fade-up">{{ __st('search.popular') }}</h2>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" data-reveal-stagger>
                            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $popular])
                        </div>
                    </section>
                @endif
            @else
                <div id="elo-listing-grid" class="grid grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-x-3 lg:gap-y-6 xl:grid-cols-5" data-reveal-stagger>
                    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'tag' => $cardTag ?? null, 'eager' => $query->page === 1])
                </div>
                <div class="mt-4">
                    <x-sf::section.load-more :url="$nextUrl" target="#elo-listing-grid" />
                </div>
            @endif
        </div>
    </div>

    {{-- Phone floating "filter | sort by" (m 1:21453) --}}
    <div class="elo-fab lg:hidden">
        <div>
            <button type="button" data-sheet-open="elo-filters"><x-ui::icon name="filter" :size="18" />{{ __st('elora_pages.listing.filter') }}@if ($filtersActive)<span class="grid size-5 place-items-center rounded-full bg-white text-[11px] text-elo-primary">{{ $filtersActive }}</span>@endif</button>
            <span aria-hidden="true"></span>
            <button type="button" data-sheet-open="elo-sort"><x-ui::icon name="sort" :size="18" />{{ __st('elora_pages.listing.sort_by') }}</button>
        </div>
    </div>

    <x-sf::modal id="elo-filters" :title="__st('catalog.filters.title')" size="sm" data-sheet-mode="always" class="elo-sheet-tall flex flex-col bg-elo-soft">
        @include('storefront::themes.elora.partials.filters', ['mode' => 'sheet'])
    </x-sf::modal>

    <x-sf::modal id="elo-sort" :title="__st('catalog.sort.label')" size="sm" data-sheet-mode="always">
        <ul class="grid" role="list">
            @foreach ($sortOptions as $sort)
                <li>
                    <a href="{{ $sortUrl($sort) }}" class="elo-sort-option" @if ($query->sort === $sort) aria-current="true" @endif>
                        {{ __st('catalog.sort.'.$sort->value) }}
                        <span class="elo-radio" aria-hidden="true">@if ($query->sort === $sort)<x-ui::icon name="check" :size="14" />@endif</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-sf::modal>
</x-sf::layout>
