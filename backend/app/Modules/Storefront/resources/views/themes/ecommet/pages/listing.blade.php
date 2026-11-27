{{--
    Ecommet product listing — one template, six entry points (Figma 1:1191 + variant notes
    137:113051; category 1:1693 / m 1:2979 with filters 1:1796): best selling (period tabs),
    new in, top rated, offers, search results and categories. Load more appends the next
    page; empty states per entry point.
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
@endphp
<x-sf::layout :seo="$seo" :page="'listing-'.$kind->value">
    <div class="eco-wide pb-12 pt-4 lg:pt-8">
        @if ($isCategory || $kind === ListingKind::Search)
            <nav aria-label="{{ __st('shell.breadcrumb') }}" @class(['text-sm text-eco-gray', 'sr-only' => $isCategory, 'mb-4' => ! $isCategory])>
                <ol class="flex flex-wrap items-center gap-1">
                    @foreach ($crumbs as $crumb)
                        <li class="inline-flex items-center gap-1">
                            @if (! $loop->last)<a href="{{ $crumb['url'] }}" class="hover:text-eco-black hover:underline">{{ $crumb['name'] }}</a><x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" />@else<span class="text-eco-black" aria-current="page">{{ $crumb['name'] }}</span>@endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($isCategory && ($subcategories !== [] || ($parentCategory ?? null) !== null))
            {{-- Sub-category circles (Figma 1:1695): 85px tiles, 81px circles, wrapping rows. --}}
            <div class="mb-6 border-b border-[#c4c4c4] pb-6 lg:mb-9 lg:pb-9">
                <ul class="flex flex-wrap gap-x-4 gap-y-6 lg:gap-x-[39px] lg:gap-y-[39px]" role="list" data-reveal-stagger>
                    @if ($category ?? null)
                        {{-- Back tile (old Ecommet): to the parent category, or to all categories from a top-level one. --}}
                        <li class="w-[76px] lg:w-[85px]" data-reveal="zoom">
                            <a href="{{ ($parentCategory ?? null) ? route('storefront.category', ['slug' => $parentCategory->slug]) : route('storefront.category.all') }}" class="eco-cat-tile" rel="up" title="{{ ($parentCategory ?? null) ? $parentCategory->name : __st('catalog.categories.title') }}">
                                <span class="eco-cat-ph eco-cat-back" aria-hidden="true"><x-ui::icon name="chevron-left" :size="32" class="rtl:-scale-x-100" /></span>
                                <span class="line-clamp-2">{{ __st('shell.back') }}</span>
                            </a>
                        </li>
                    @endif
                    @foreach ($subcategories as $sub)
                        <li class="w-[76px] lg:w-[85px]" data-reveal="zoom">
                            <a href="{{ route('storefront.category', ['slug' => $sub->slug]) }}" class="eco-cat-tile" @if ($sub->id === ($category->id ?? null)) aria-current="page" @endif>
                                <x-sf::category-image :category="$sub" :size="82" :eager="$loop->index < 8" />
                                <span class="line-clamp-2">{{ $sub->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($isCategory)
            @php
                $facets = $result->facets;
                $chips = array_values(array_filter([
                    $facets['price'] !== [] ? ['key' => 'price', 'label' => __st('catalog.filters.price'), 'active' => $query->priceMin !== null || $query->priceMax !== null] : null,
                    ...array_map(static fn (array $attribute): array => ['key' => 'opt-'.$attribute['id'], 'label' => $attribute['name'], 'active' => isset($query->options[$attribute['id']])], $facets['options']),
                    $facets['rating'] !== [] ? ['key' => 'rating', 'label' => __st('catalog.filters.rating'), 'active' => (bool) $query->minRating] : null,
                    ['key' => 'availability', 'label' => __st('catalog.filters.availability'), 'active' => $query->inStockOnly],
                ]));
            @endphp
            <h1 class="sr-only">{{ $title }} ({{ $result->total }})</h1>
            {{-- Filter chip row (Figma 1:1777): Filter, Sort by, then one chip per facet opening its accordion; swipes / drags sideways. --}}
            <div class="relative mb-6 lg:mb-10">
                <div class="swiper" data-rail='{"spaceBetween":8,"freeMode":{"enabled":true,"sticky":false}}'>
                    <div class="swiper-wrapper">
                        <div class="swiper-slide !w-auto">
                            <button type="button" class="eco-filter-chip" data-sheet-open="sf-filters" @if ($filtersActive) data-active @endif>
                                <x-ui::icon name="filter" :size="16" />{{ __st('catalog.filters.title') }}@if ($filtersActive)<span class="grid size-5 place-items-center rounded-full bg-eco-black text-[11px] text-white">{{ $filtersActive }}</span>@endif
                            </button>
                        </div>
                        <div class="swiper-slide !w-auto">
                            <label class="eco-filter-chip relative cursor-pointer pe-9">
                                <span class="sr-only">{{ __st('catalog.sort.label') }}</span>
                                <select id="sf-sort" class="eco-chip-select" data-auto-filter="sort">
                                    @foreach ($sortOptions as $sort)
                                        <option value="{{ $sort->value }}" @selected($query->sort === $sort)>{{ __st('catalog.sort.prefix') }} {{ __st('catalog.sort.'.$sort->value) }}</option>
                                    @endforeach
                                </select>
                                <x-ui::icon name="chevron-down" :size="16" class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2" />
                            </label>
                        </div>
                        @foreach ($chips as $chip)
                            <div class="swiper-slide !w-auto">
                                <button type="button" class="eco-filter-chip" data-sheet-open="sf-filters" data-facet-chip="{{ $chip['key'] }}" @if ($chip['active']) data-active @endif>
                                    {{ $chip['label'] }}<x-ui::icon name="chevron-down" :size="16" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
        {{-- Title row + quick filters --}}
        <div class="mb-6 flex flex-wrap items-center gap-x-4 gap-y-3 lg:mb-10">
            <h1 class="text-base text-eco-black lg:text-lg">{{ $title }}@if (! $result->isEmpty() && $kind !== ListingKind::Search)<span class="ms-1 text-sm text-eco-gray">({{ $result->total }})</span>@endif</h1>

            @if ($kind === ListingKind::BestSelling)
                <div class="-mx-1 flex gap-3 overflow-x-auto px-1 py-1 [scrollbar-width:none]" role="group" aria-label="{{ __st('catalog.listing.best_selling.period') }}">
                    @foreach (SalesPeriod::cases() as $period)
                        <a href="{{ request()->fullUrlWithQuery(['period' => $period->value, 'page' => null, 'fragment' => null]) }}" class="eco-pill-outline h-9 shrink-0 text-sm lg:text-base" @if ($query->period === $period) aria-current="true" @endif>{{ __st('catalog.listing.best_selling.within', ['days' => $period->value]) }}</a>
                    @endforeach
                </div>
            @endif

            <div class="ms-auto flex flex-wrap items-center gap-3">
                <button type="button" class="eco-filter-chip" data-sheet-open="sf-filters" @if ($filtersActive) data-active @endif>
                    <x-ui::icon name="filter" :size="18" />{{ __st('catalog.filters.title') }}@if ($filtersActive)<span class="grid size-5 place-items-center rounded-full bg-eco-black text-[11px] text-white">{{ $filtersActive }}</span>@endif
                </button>
                @if ($categoryOptions !== [] && ! $isCategory)
                    <label class="hidden text-base text-eco-gray sm:inline" for="sf-cat-filter">{{ __st('catalog.filters.by_category') }}</label>
                    <select id="sf-cat-filter" class="eco-select" data-auto-filter="cat[]">
                        <option value="">{{ __st('catalog.filters.all_categories') }}</option>
                        @foreach ($categoryOptions as $option)
                            <option value="{{ $option['id'] }}" @selected(in_array($option['id'], $query->categoryIds, true))>{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="sr-only" for="sf-sort">{{ __st('catalog.sort.label') }}</label>
                <select id="sf-sort" class="eco-select" data-auto-filter="sort">
                    @foreach ($sortOptions as $sort)
                        <option value="{{ $sort->value }}" @selected($query->sort === $sort)>{{ __st('catalog.sort.prefix') }} {{ __st('catalog.sort.'.$sort->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @endif

        @if ($result->isEmpty())
            <section class="grid justify-items-center gap-4 py-16 text-center" data-reveal="fade-up">
                <x-ui::icon :name="$kind === ListingKind::Search ? 'search' : 'box'" :size="48" class="text-eco-semi" />
                <h2 class="text-xl font-medium text-eco-black">
                    {{ $filtersActive ? __st('catalog.empty.filtered_title') : __st('catalog.empty.'.$kind->value.'_title', ['term' => $query->term ?? '']) }}
                </h2>
                <p class="max-w-md text-sm text-eco-gray">{{ $filtersActive ? __st('catalog.empty.filtered_body') : __st('catalog.empty.'.$kind->value.'_body') }}</p>
                <div class="flex flex-wrap justify-center gap-3">
                    @if ($filtersActive)
                        <a href="{{ url()->current().($query->term ? '?q='.urlencode($query->term) : '') }}" class="eco-pill-dark">{{ __st('catalog.filters.reset') }}</a>
                    @elseif ($kind === ListingKind::BestSelling || $kind === ListingKind::TopRated)
                        <a href="{{ route('storefront.new-in') }}" class="eco-pill-dark">{{ __st('catalog.empty.show_new') }}</a>
                    @else
                        <a href="{{ route('storefront.new-in') }}" class="eco-pill-dark">{{ __st('catalog.empty.browse_all') }}</a>
                    @endif
                </div>
                @if ($kind === ListingKind::Offers && ! $filtersActive && view()->exists('storefront::themes.ecommet.partials.newsletter-signup') && Route::has('storefront.newsletter.store'))
                    <div class="mt-6 w-full max-w-3xl text-start">@include('storefront::themes.ecommet.partials.newsletter-signup')</div>
                @endif
            </section>
            @if ($popular !== [])
                <section class="grid gap-6" aria-labelledby="sf-popular-title">
                    <h2 id="sf-popular-title" class="eco-section-title justify-self-center text-eco-black">{{ __st('search.popular') }}</h2>
                    <div class="eco-card-grid lg:px-7">
                        @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $popular])
                    </div>
                </section>
            @endif
        @else
            <div id="sf-listing-grid" class="eco-card-grid lg:px-7" data-reveal-stagger>
                @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'tag' => $cardTag ?? null, 'eager' => $query->page === 1])
            </div>
            <div class="mt-10">
                <x-sf::section.load-more :url="$nextUrl" target="#sf-listing-grid" />
            </div>
        @endif
    </div>
    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.filters'))
</x-sf::layout>
