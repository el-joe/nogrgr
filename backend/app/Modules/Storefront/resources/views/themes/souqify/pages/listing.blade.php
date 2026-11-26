{{--
    Souqify product listing — one template, six entry points (Figma 1:75532 / m 1:76311,
    offers m 1:77354, variant notes 137:114609): search results, best selling (period pills),
    new in, top rated, offers and categories. Desktop: white filter sidebar on the start edge
    (applies on change), title + item count, "Sort by:" pills, 3-up grid and numbered
    pagination. Phones: back + search bar, title + count, swipeable sort pills with a filter
    pill that opens the full-height filter sheet, 2-up grid.
    Data as the Ecommet listing (RendersListings): $kind, $title, $crumbs, $query, $result,
    $nextUrl, $category, $subcategories, $popular, $cardTag, $seo.
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
    $sortUrl = fn ($sort) => request()->fullUrlWithQuery(['sort' => $sort->value === $kind->defaultSort()->value ? null : $sort->value, 'page' => null, 'fragment' => null]);
    $pages = $result->perPage > 0 ? (int) ceil($result->total / $result->perPage) : 1;
    $current = min(max(1, $query->page), max(1, $pages));
    $pageUrl = fn (int $page) => request()->fullUrlWithQuery(['page' => $page > 1 ? $page : null, 'fragment' => null]);
    $window = collect([1, $current - 1, $current, $current + 1, $pages])->filter(fn ($n) => $n >= 1 && $n <= $pages)->unique()->sort()->values();
    $description = $isCategory ? trim(strip_tags((string) ($category->description ?? ''))) : '';
@endphp
<x-sf::layout :seo="$seo" :page="'listing-'.$kind->value" :nav="$isCategory ? 'categories' : null">
    {{-- Phone bar: back + search (m 1:76311) --}}
    <div class="sq-wrap flex items-center gap-2.5 pt-3 lg:hidden">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('storefront.home') }}" class="sq-icon-btn" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="chevron-left" :size="20" class="rtl:-scale-x-100" /></a>
        <form method="GET" action="{{ route('storefront.search') }}" role="search" class="flex h-10 min-w-0 flex-1 items-center gap-2 rounded-full border border-sq-line bg-white px-3">
            <x-ui::icon name="search" :size="18" class="shrink-0 text-sf-muted" />
            <label for="sq-m-search" class="sr-only">{{ __st('shell.search_submit') }}</label>
            <input id="sq-m-search" type="search" name="q" value="{{ $query->term }}" placeholder="{{ __st('shell.search') }}" class="h-full min-w-0 flex-1 bg-transparent text-sm text-sq-ink outline-none" enterkeyhint="search" autocomplete="off">
        </form>
    </div>

    <div class="grid lg:grid-cols-[339px_minmax(0,1fr)]">
        {{-- Desktop filter sidebar (1:75534) --}}
        <aside class="sq-filters hidden self-stretch px-10 pb-10 pt-8 lg:block" aria-label="{{ __st('catalog.filters.title') }}" data-reveal="slide-start">
            @include('storefront::themes.souqify.partials.filters', ['mode' => 'sidebar'])
        </aside>

        <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] content-start gap-4 px-4 pb-16 pt-4 lg:gap-6 lg:pe-[108px] lg:ps-16 lg:pt-6">
            @if ($isCategory || $kind === ListingKind::Search)
                <nav aria-label="{{ __st('shell.breadcrumb') }}" class="hidden lg:block">
                    <ol class="sq-crumbs">
                        @foreach ($crumbs as $crumb)
                            <li class="inline-flex items-center gap-1.5">
                                @if (! $loop->last)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a><x-ui::icon name="chevron-right" :size="12" class="rtl:-scale-x-100" />@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <header class="grid gap-1 lg:gap-2" data-reveal="fade-up">
                <div class="flex items-baseline justify-between gap-3 lg:block">
                    <h1 class="text-xl font-medium text-[#242424] lg:text-[32px]">{{ $title }}</h1>
                    @unless ($result->isEmpty())
                        <p class="shrink-0 text-xs text-sq-primary lg:hidden">{{ __st('souqify_pages.listing.items', ['count' => number_format($result->total)]) }}</p>
                    @endunless
                </div>
                @if ($description !== '')
                    <p class="text-sm text-sq-sub lg:text-base">{{ \Illuminate\Support\Str::limit($description, 160) }}</p>
                @endif
                @unless ($result->isEmpty())
                    <p class="hidden text-base text-sq-primary lg:block">{{ __st('souqify_pages.listing.items', ['count' => number_format($result->total)]) }}</p>
                @endunless
            </header>

            {{-- Sort pills (1:75676); phones: swipeable with the filter pill first --}}
            <div class="flex items-center gap-4">
                <p class="hidden shrink-0 text-base text-[#434655] lg:block">{{ __st('souqify_pages.listing.sort_by') }}</p>
                <p class="shrink-0 text-xs text-[#434655] lg:hidden">{{ __st('souqify_pages.listing.sort_by') }}</p>
                <div class="swiper min-w-0 flex-1" data-rail='{"spaceBetween":8,"freeMode":{"enabled":true,"sticky":false}}'>
                    <ul class="swiper-wrapper" role="list" aria-label="{{ __st('catalog.sort.label') }}">
                        <li class="swiper-slide !w-auto lg:hidden">
                            <button type="button" class="sq-sort-pill mo-press" data-sheet-open="sq-filters"><x-ui::icon name="filter" :size="14" />{{ __st('souqify_pages.listing.filter') }}@if ($filtersActive)<span class="grid size-4 place-items-center rounded-full bg-sq-primary text-[10px] text-white">{{ $filtersActive }}</span>@endif</button>
                        </li>
                        @foreach ($sortOptions as $sort)
                            <li class="swiper-slide !w-auto">
                                <a href="{{ $sortUrl($sort) }}" class="sq-sort-pill mo-press" @if ($query->sort === $sort) aria-current="true" @endif>{{ __st('catalog.sort.'.$sort->value) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @if ($kind === ListingKind::BestSelling)
                <div class="flex gap-2 overflow-x-auto [scrollbar-width:none]" role="group" aria-label="{{ __st('catalog.listing.best_selling.period') }}">
                    @foreach (SalesPeriod::cases() as $period)
                        <a href="{{ request()->fullUrlWithQuery(['period' => $period->value, 'page' => null, 'fragment' => null]) }}" class="sq-sort-pill sq-sort-pill-ghost mo-press" @if ($query->period === $period) aria-current="true" @endif>{{ __st('catalog.listing.best_selling.within', ['days' => $period->value]) }}</a>
                    @endforeach
                </div>
            @endif

            @if ($subcategories !== [])
                <nav class="swiper w-full" data-rail='{"spaceBetween":8,"freeMode":{"enabled":true,"sticky":false}}' aria-label="{{ __st('shell.nav.categories') }}">
                    <ul class="swiper-wrapper" role="list">
                        @foreach ($subcategories as $sub)
                            <li class="swiper-slide !w-auto"><a href="{{ route('storefront.category', ['slug' => $sub->slug]) }}" class="sq-sort-pill sq-sort-pill-ghost mo-press">{{ $sub->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($filtersActive)
                <a href="{{ url()->current().($query->term ? '?q='.urlencode($query->term) : '') }}" class="inline-flex w-fit items-center gap-1 text-sm text-sq-primary hover:underline lg:hidden"><x-ui::icon name="x" :size="14" />{{ __st('souqify_pages.listing.clear_all') }} ({{ $filtersActive }})</a>
            @endif

            @if ($result->isEmpty())
                <section class="sq-empty sq-card" data-reveal="fade-up">
                    <span class="sq-empty-icon"><x-ui::icon :name="$kind === ListingKind::Search ? 'search' : 'box'" :size="40" /></span>
                    <h2>{{ $filtersActive ? __st('catalog.empty.filtered_title') : __st('catalog.empty.'.$kind->value.'_title', ['term' => $query->term ?? '']) }}</h2>
                    <p class="max-w-md">{{ $filtersActive ? __st('catalog.empty.filtered_body') : __st('catalog.empty.'.$kind->value.'_body') }}</p>
                    <div class="mt-2 flex flex-wrap justify-center gap-3">
                        @if ($filtersActive)
                            <a href="{{ url()->current().($query->term ? '?q='.urlencode($query->term) : '') }}" class="sq-btn sq-btn-primary mo-press">{{ __st('catalog.filters.reset') }}</a>
                        @elseif ($kind === ListingKind::BestSelling || $kind === ListingKind::TopRated)
                            <a href="{{ route('storefront.new-in') }}" class="sq-btn sq-btn-primary mo-press">{{ __st('catalog.empty.show_new') }}</a>
                        @else
                            <a href="{{ route('storefront.new-in') }}" class="sq-btn sq-btn-primary mo-press">{{ __st('catalog.empty.browse_all') }}</a>
                        @endif
                    </div>
                    @if ($kind === ListingKind::Offers && ! $filtersActive && Route::has('storefront.newsletter.store'))
                        <div class="mt-6 w-full max-w-3xl text-start">@include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.newsletter-signup'))</div>
                    @endif
                </section>
                @if ($popular !== [])
                    <section class="grid gap-4" aria-labelledby="sq-popular-title">
                        <h2 id="sq-popular-title" class="text-lg font-medium text-sq-ink lg:text-2xl" data-reveal="fade-up">{{ __st('search.popular') }}</h2>
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 lg:gap-4" data-reveal-stagger>
                            @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $popular])
                        </div>
                    </section>
                @endif
            @else
                <div id="sq-listing-grid" class="grid grid-cols-2 gap-2.5 lg:grid-cols-3 lg:gap-4" data-reveal-stagger>
                    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'tag' => $cardTag ?? null, 'eager' => $query->page === 1])
                </div>

                @if ($pages > 1)
                    {{-- Numbered pagination (1:75700); real links, so crawlers and no-JS visitors page too --}}
                    <nav class="mt-6 flex items-center justify-center gap-2" aria-label="{{ __st('souqify_pages.listing.pagination') }}" data-reveal="fade">
                        @if ($current > 1)
                            <a href="{{ $pageUrl($current - 1) }}" class="sq-page" rel="prev" aria-label="{{ __st('souqify_pages.listing.previous') }}"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" /></a>
                        @endif
                        @foreach ($window as $number)
                            @if (! $loop->first && $number - $window[$loop->index - 1] > 1)
                                <span class="px-2 text-[#737686]" aria-hidden="true">…</span>
                            @endif
                            <a href="{{ $pageUrl($number) }}" class="sq-page" @if ($number === $current) aria-current="page" @endif>{{ $number }}</a>
                        @endforeach
                        @if ($current < $pages)
                            <a href="{{ $pageUrl($current + 1) }}" class="sq-page" rel="next" aria-label="{{ __st('souqify_pages.listing.next') }}"><x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </div>

    <x-sf::modal id="sq-filters" :title="__st('catalog.filters.title')" size="sm" data-sheet-mode="always" class="sq-sheet-tall flex flex-col bg-white">
        @include('storefront::themes.souqify.partials.filters', ['mode' => 'sheet'])
    </x-sf::modal>
</x-sf::layout>
