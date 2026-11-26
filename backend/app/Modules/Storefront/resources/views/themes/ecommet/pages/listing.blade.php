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
            <nav aria-label="{{ __st('shell.breadcrumb') }}" class="mb-4 text-sm text-eco-gray">
                <ol class="flex flex-wrap items-center gap-1">
                    @foreach ($crumbs as $crumb)
                        <li class="inline-flex items-center gap-1">
                            @if (! $loop->last)<a href="{{ $crumb['url'] }}" class="hover:text-eco-black hover:underline">{{ $crumb['name'] }}</a><x-ui::icon name="chevron-right" :size="14" class="rtl:-scale-x-100" />@else<span class="text-eco-black" aria-current="page">{{ $crumb['name'] }}</span>@endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($isCategory && $subcategories !== [])
            <div class="mb-6 border-b border-[#c4c4c4] pb-6">
                <div class="swiper" data-rail='{"spaceBetween":24}'>
                    <ul class="swiper-wrapper" role="list">
                        @foreach ($subcategories as $sub)
                            <li class="swiper-slide !w-[96px]">
                                <a href="{{ route('storefront.category', ['slug' => $sub->slug]) }}" class="eco-cat-tile" @if ($sub->id === $category->id) aria-current="page" @endif>
                                    <x-sf::category-image :category="$sub" :size="82" :eager="$loop->index < 8" />
                                    <span class="line-clamp-2">{{ $sub->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4 lg:px-7">
                        @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $popular])
                    </div>
                </section>
            @endif
        @else
            <div id="sf-listing-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4 lg:px-7" data-reveal-stagger>
                @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.card-grid-items'), ['cards' => $result->cards, 'tag' => $cardTag ?? null, 'eager' => $query->page === 1])
            </div>
            <div class="mt-10">
                <x-sf::section.load-more :url="$nextUrl" target="#sf-listing-grid" />
            </div>
        @endif
    </div>
    @include(\App\Modules\Storefront\Support\StorefrontView::resolve('partials.filters'))
</x-sf::layout>
